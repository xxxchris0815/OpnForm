<?php

namespace App\Jobs\Form;

use App\Models\Forms\Form;
use App\Service\Forms\PartialSubmissionAbandonmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MarkAbandonedFormSubmissionsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    public int $uniqueFor = 3600;

    public function __construct(public int $formId)
    {
    }

    public function handle(PartialSubmissionAbandonmentService $abandonmentService): void
    {
        $form = Form::withTrashed()->find($this->formId);

        if (
            !$form
            || !$form->enable_partial_submissions
            || !$form->partial_submission_abandonment_value
            || !$form->partial_submission_abandonment_unit
        ) {
            return;
        }

        $marked = $abandonmentService->markAbandoned($form);

        if ($marked > 0) {
            Log::info('Partial form submissions marked as abandoned', [
                'form_id' => $form->id,
                'abandoned_submissions' => $marked,
            ]);
        }
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("partial-submission-abandonment:{$this->formId}"))
                ->releaseAfter(60)
                ->expireAfter(3900),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->formId;
    }

    public function backoff(): array
    {
        return [60, 180];
    }
}
