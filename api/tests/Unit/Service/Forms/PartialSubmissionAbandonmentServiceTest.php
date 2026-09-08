<?php

use App\Models\Forms\Form;
use App\Service\Forms\PartialSubmissionAbandonmentService;
use Carbon\CarbonImmutable;

it('computes an abandonment cutoff from minutes, hours, and days', function () {
    $service = app(PartialSubmissionAbandonmentService::class);
    $reference = CarbonImmutable::parse('2026-09-08 12:00:00');

    $form = new Form([
        'enable_partial_submissions' => true,
        'partial_submission_abandonment_value' => 30,
        'partial_submission_abandonment_unit' => 'minute',
    ]);
    expect($service->cutoff($form, $reference)->toDateTimeString())->toBe('2026-09-08 11:30:00');

    $form->partial_submission_abandonment_value = 2;
    $form->partial_submission_abandonment_unit = 'hour';
    expect($service->cutoff($form, $reference)->toDateTimeString())->toBe('2026-09-08 10:00:00');

    $form->partial_submission_abandonment_value = 1;
    $form->partial_submission_abandonment_unit = 'day';
    expect($service->cutoff($form, $reference)->toDateTimeString())->toBe('2026-09-07 12:00:00');
});

it('returns no cutoff when the abandonment timeout is not configured', function () {
    $service = app(PartialSubmissionAbandonmentService::class);
    $form = new Form([
        'enable_partial_submissions' => true,
        'partial_submission_abandonment_value' => null,
        'partial_submission_abandonment_unit' => null,
    ]);

    expect($service->cutoff($form))->toBeNull();
});
