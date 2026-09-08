<?php

namespace App\Integrations\Handlers;

use App\Events\Forms\FormSubmitted;
use App\Models\Forms\Form;
use Illuminate\Validation\Rule;

class PartialWebhookIntegration extends WebhookIntegration
{
    public static function supportedEvents(): array
    {
        return [
            FormSubmitted::EVENT_PARTIAL,
            FormSubmitted::EVENT_ABANDONED,
        ];
    }

    public static function getValidationRules(?Form $form): array
    {
        return array_merge(parent::getValidationRules($form), [
            'events' => ['nullable', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::in(['partial', 'abandoned'])],
        ]);
    }

    protected function shouldRun(): bool
    {
        if (!parent::shouldRun()) {
            return false;
        }

        $eventKey = $this->eventKey();

        return $eventKey && in_array($eventKey, $this->configuredEvents(), true);
    }

    protected function getWebhookData(): array
    {
        $data = parent::getWebhookData();
        $data['event'] = $this->event->eventType;
        $data['status'] = $this->eventKey() ?? 'partial';

        if (($this->submissionMeta['abandoned_at'] ?? null) && $this->event->eventType === FormSubmitted::EVENT_ABANDONED) {
            $data['abandoned_at'] = $this->submissionMeta['abandoned_at'];
        }

        return $data;
    }

    public static function formatData(array $data): array
    {
        $events = $data['data']['events'] ?? ['abandoned'];
        $data = parent::formatData($data);

        if (!isset($data['data']) || !is_array($data['data'])) {
            return $data;
        }

        if (!is_array($events) || $events === []) {
            $events = ['abandoned'];
        }

        $data['data']['events'] = array_values(array_unique(array_values($events)));

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function configuredEvents(): array
    {
        $events = $this->integrationData->events ?? ['abandoned'];

        if (is_object($events)) {
            $events = (array) $events;
        }

        if (!is_array($events) || $events === []) {
            return ['abandoned'];
        }

        return array_values(array_unique(array_map('strval', $events)));
    }

    private function eventKey(): ?string
    {
        return match ($this->event->eventType) {
            FormSubmitted::EVENT_PARTIAL => 'partial',
            FormSubmitted::EVENT_ABANDONED => 'abandoned',
            default => null,
        };
    }
}
