<?php

use App\Jobs\Form\MarkAbandonedFormSubmissionsJob;
use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\Integration\FormIntegration;
use App\Models\Integration\FormIntegrationsEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

afterEach(function () {
    Carbon::setTestNow();
});

function createPartialAbandonmentForm($test, array $attributes = [])
{
    $user = $test->actingAsBusinessUser();
    $workspace = $test->createUserWorkspace($user);

    return $test->createForm($user, $workspace, array_merge([
        'enable_partial_submissions' => true,
        'partial_submission_abandonment_value' => 30,
        'partial_submission_abandonment_unit' => 'minute',
    ], $attributes));
}

function createStalePartialSubmission($test, Form $form, Carbon $updatedAt, array $data = [])
{
    $submissionData = $test->generateFormSubmissionData($form, $data);
    $submissionData['is_partial'] = true;

    $response = $test->postJson(route('forms.answer', $form->slug), $submissionData)
        ->assertSuccessful();

    $submission = $form->submissions()->first();
    $submission->timestamps = false;
    $submission->updated_at = $updatedAt;
    $submission->saveQuietly();
    $submission->timestamps = true;

    return [$submission, $response->json('submission_hash')];
}

it('stores an abandonment timeout through the form API', function () {
    $form = createPartialAbandonmentForm($this, [
        'partial_submission_abandonment_value' => null,
        'partial_submission_abandonment_unit' => null,
    ]);
    $formData = (new \App\Http\Resources\FormResource($form))->toArray(request());
    $formData['partial_submission_abandonment_value'] = 15;
    $formData['partial_submission_abandonment_unit'] = 'minute';

    $this->putJson(route('open.forms.update', $form), $formData)
        ->assertSuccessful()
        ->assertJsonPath('form.partial_submission_abandonment_value', 15)
        ->assertJsonPath('form.partial_submission_abandonment_unit', 'minute');
});

it('marks stale partial submissions as abandoned after the timeout', function () {
    $form = createPartialAbandonmentForm($this);
    [$submission] = createStalePartialSubmission($this, $form, now()->subHour(), ['text' => 'Draft']);

    $this->artisan('forms:mark-abandoned-submissions')
        ->assertSuccessful();

    expect($submission->fresh()->status)->toBe(FormSubmission::STATUS_ABANDONED)
        ->and($submission->fresh()->meta['abandoned_at'] ?? null)->not->toBeEmpty();
});

it('does not mark recent partial submissions as abandoned', function () {
    $form = createPartialAbandonmentForm($this);
    [$submission] = createStalePartialSubmission($this, $form, now()->subMinutes(5), ['text' => 'Still typing']);

    $this->artisan('forms:mark-abandoned-submissions')
        ->assertSuccessful();

    expect($submission->fresh()->status)->toBe(FormSubmission::STATUS_PARTIAL);
});

it('does not mark submissions as abandoned when no timeout is configured', function () {
    $form = createPartialAbandonmentForm($this, [
        'partial_submission_abandonment_value' => null,
        'partial_submission_abandonment_unit' => null,
    ]);
    [$submission] = createStalePartialSubmission($this, $form, now()->subDays(2), ['text' => 'Draft']);

    $this->artisan('forms:mark-abandoned-submissions')
        ->assertSuccessful();

    expect($submission->fresh()->status)->toBe(FormSubmission::STATUS_PARTIAL);
});

it('counts abandoned submissions in a dry run without changing them', function () {
    $form = createPartialAbandonmentForm($this);
    [$submission] = createStalePartialSubmission($this, $form, now()->subHour(), ['text' => 'Draft']);

    Queue::fake();

    $this->artisan('forms:mark-abandoned-submissions', ['--dry-run' => true])
        ->assertSuccessful();

    Queue::assertNotPushed(MarkAbandonedFormSubmissionsJob::class);
    expect($submission->fresh()->status)->toBe(FormSubmission::STATUS_PARTIAL);
});

it('sends the dedicated webhook once when a submission is abandoned', function () {
    Http::fake([
        'https://example.com/*' => Http::response(['ok' => true], 200),
    ]);

    $form = createPartialAbandonmentForm($this);
    $this->postJson(route('open.forms.integrations.create', $form), [
        'status' => 'active',
        'integration_id' => 'partial_webhook',
        'logic' => null,
        'data' => [
            'webhook_url' => 'https://example.com/abandoned',
            'events' => ['abandoned'],
        ],
    ])->assertSuccessful();

    $completedWebhook = FormIntegration::factory()->for($form)->create([
        'integration_id' => 'webhook',
        'status' => FormIntegration::STATUS_ACTIVE,
        'data' => [
            'webhook_url' => 'https://example.com/completed',
        ],
    ]);

    createStalePartialSubmission($this, $form, now()->subHour(), ['text' => 'Left early']);

    $this->artisan('forms:mark-abandoned-submissions')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        $payload = json_decode($request->body(), true);

        return $request->url() === 'https://example.com/abandoned'
            && ($payload['event'] ?? null) === 'submission.abandoned'
            && ($payload['status'] ?? null) === 'abandoned';
    });
    Http::assertNotSent(function ($request) {
        return $request->url() === 'https://example.com/completed';
    });

    $partialWebhook = FormIntegration::where('form_id', $form->id)
        ->where('integration_id', 'partial_webhook')
        ->first();

    $this->assertDatabaseHas('form_integrations_events', [
        'integration_id' => $partialWebhook->id,
        'status' => FormIntegrationsEvent::STATUS_SUCCESS,
    ]);
    $this->assertDatabaseMissing('form_integrations_events', [
        'integration_id' => $completedWebhook->id,
    ]);
});

it('sends the dedicated webhook when a partial submission is first saved', function () {
    Http::fake([
        'https://example.com/*' => Http::response(['ok' => true], 200),
    ]);

    $form = createPartialAbandonmentForm($this);
    $this->postJson(route('open.forms.integrations.create', $form), [
        'status' => 'active',
        'integration_id' => 'partial_webhook',
        'logic' => null,
        'data' => [
            'webhook_url' => 'https://example.com/partial',
            'events' => ['partial'],
        ],
    ])->assertSuccessful();

    $formData = $this->generateFormSubmissionData($form, ['text' => 'First save']);
    $formData['is_partial'] = true;
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        $payload = json_decode($request->body(), true);

        return $request->url() === 'https://example.com/partial'
            && ($payload['event'] ?? null) === 'submission.partial'
            && ($payload['status'] ?? null) === 'partial';
    });

    $hash = FormSubmission::first()->public_id;
    $update = $this->generateFormSubmissionData($form, ['text' => 'Second save']);
    $update['is_partial'] = true;
    $update['submission_hash'] = $hash;
    Http::fake([
        'https://example.com/*' => Http::response(['ok' => true], 200),
    ]);

    $this->postJson(route('forms.answer', $form->slug), $update)
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('does not send completed webhooks for partial saves', function () {
    Http::fake([
        'https://example.com/*' => Http::response(['ok' => true], 200),
    ]);

    $form = createPartialAbandonmentForm($this);
    FormIntegration::factory()->for($form)->create([
        'integration_id' => 'webhook',
        'status' => FormIntegration::STATUS_ACTIVE,
        'data' => [
            'webhook_url' => 'https://example.com/completed',
        ],
    ]);

    $formData = $this->generateFormSubmissionData($form, ['text' => 'Draft']);
    $formData['is_partial'] = true;
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('lets a respondent complete an abandoned submission with the original hash', function () {
    $form = createPartialAbandonmentForm($this);
    [$submission, $hash] = createStalePartialSubmission($this, $form, now()->subHour(), ['text' => 'Draft']);

    $this->artisan('forms:mark-abandoned-submissions')
        ->assertSuccessful();

    expect($submission->fresh()->status)->toBe(FormSubmission::STATUS_ABANDONED);

    $completeData = $this->generateFormSubmissionData($form, ['text' => 'Finished later']);
    $completeData['submission_hash'] = $hash;

    $this->postJson(route('forms.answer', $form->slug), $completeData)
        ->assertSuccessful();

    expect($submission->fresh()->status)->toBe(FormSubmission::STATUS_COMPLETED);
});
