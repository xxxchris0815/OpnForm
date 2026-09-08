<?php

namespace App\Console\Commands;

use App\Jobs\Form\MarkAbandonedFormSubmissionsJob;
use App\Models\Forms\Form;
use App\Service\Forms\PartialSubmissionAbandonmentService;
use Illuminate\Console\Command;
use Throwable;

class MarkAbandonedFormSubmissions extends Command
{
    protected $signature = 'forms:mark-abandoned-submissions
        {--form= : Only process a specific form ID}
        {--dry-run : Count expired partial submissions without marking them}';

    protected $description = 'Mark stale partial submissions as abandoned after the form timeout';

    public function handle(PartialSubmissionAbandonmentService $abandonmentService): int
    {
        $query = Form::withTrashed()
            ->where('enable_partial_submissions', true)
            ->whereNotNull('partial_submission_abandonment_value')
            ->whereNotNull('partial_submission_abandonment_unit');

        if ($formId = $this->option('form')) {
            $query->whereKey($formId);
        }

        $dryRun = (bool) $this->option('dry-run');
        $processedForms = 0;
        $expiredSubmissions = 0;
        $failedForms = 0;

        $query->lazyById(100)->each(function (Form $form) use (
            $abandonmentService,
            $dryRun,
            &$processedForms,
            &$expiredSubmissions,
            &$failedForms
        ) {
            $processedForms++;

            try {
                if (!$dryRun) {
                    MarkAbandonedFormSubmissionsJob::dispatch($form->id);
                    $this->line("Form {$form->id}: abandonment job dispatched.");

                    return;
                }

                $count = $abandonmentService->countExpired($form);
                $expiredSubmissions += $count;

                if ($count > 0) {
                    $this->line("Form {$form->id}: would mark {$count} partial submission(s) as abandoned.");
                }
            } catch (Throwable $exception) {
                $failedForms++;
                report($exception);
                $this->error("Form {$form->id}: abandonment failed; remaining forms will still be processed.");
            }
        });

        $summary = $dryRun
            ? "{$expiredSubmissions} submission(s) would be marked abandoned"
            : 'abandonment jobs dispatched';
        $this->info("Processed {$processedForms} form(s); {$summary}; {$failedForms} form(s) failed.");

        return $failedForms === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
