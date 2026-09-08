<?php

namespace App\Service\Forms;

use App\Events\Forms\FormSubmitted;
use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PartialSubmissionAbandonmentService
{
    public function cutoff(Form $form, ?CarbonInterface $reference = null): ?CarbonImmutable
    {
        $value = $form->partial_submission_abandonment_value;
        $unit = $form->partial_submission_abandonment_unit;

        if (!$value || !in_array($unit, Form::PARTIAL_SUBMISSION_ABANDONMENT_UNITS, true)) {
            return null;
        }

        $reference = CarbonImmutable::instance($reference ?? now());

        return match ($unit) {
            'minute' => $reference->subMinutes($value),
            'hour' => $reference->subHours($value),
            'day' => $reference->subDays($value),
            default => null,
        };
    }

    public function countExpired(Form $form, ?CarbonInterface $reference = null): int
    {
        $cutoff = $this->cutoff($form, $reference);

        if (!$cutoff || !$form->enable_partial_submissions) {
            return 0;
        }

        return $form->submissions()
            ->where('status', FormSubmission::STATUS_PARTIAL)
            ->where('updated_at', '<=', $cutoff)
            ->count();
    }

    public function markAbandoned(Form $form, ?CarbonInterface $reference = null): int
    {
        $cutoff = $this->cutoff($form, $reference);

        if (!$cutoff || !$form->enable_partial_submissions) {
            return 0;
        }

        $marked = 0;

        $form->submissions()
            ->where('status', FormSubmission::STATUS_PARTIAL)
            ->where('updated_at', '<=', $cutoff)
            ->select('id')
            ->lazyById(100)
            ->each(function (FormSubmission $submission) use ($form, &$marked) {
                $abandoned = $this->markSubmissionAbandoned($form, $submission->id);

                if ($abandoned) {
                    $marked++;
                }
            });

        return $marked;
    }

    public function markSubmissionAbandoned(Form $form, int $submissionId): bool
    {
        $payload = DB::transaction(function () use ($form, $submissionId) {
            $query = $form->submissions()->whereKey($submissionId);

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $query->lockForUpdate();
            }

            $submission = $query->first();

            if (!$submission || $submission->status !== FormSubmission::STATUS_PARTIAL) {
                return null;
            }

            $abandonedAt = now();
            $meta = $submission->meta ?? [];
            $meta['abandoned_at'] = $abandonedAt->toIso8601String();

            $submission->timestamps = false;
            $submission->status = FormSubmission::STATUS_ABANDONED;
            $submission->meta = $meta;
            $submission->saveQuietly();

            $formData = $submission->data ?? [];
            $formData['submission_id'] = $submission->id;

            return [
                'formData' => $formData,
                'meta' => $meta,
            ];
        });

        if (!$payload) {
            return false;
        }

        FormSubmitted::dispatch(
            $form,
            $payload['formData'],
            $payload['meta'],
            FormSubmitted::EVENT_ABANDONED
        );

        return true;
    }
}
