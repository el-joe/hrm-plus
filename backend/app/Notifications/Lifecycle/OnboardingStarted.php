<?php

namespace App\Notifications\Lifecycle;

use App\Models\Onboarding;
use App\Notifications\CentreNotification;

/**
 * "Your onboarding checklist is ready" to the new employee (template "onboarding_started").
 */
class OnboardingStarted extends CentreNotification
{
    public function __construct(public readonly Onboarding $onboarding) {}

    public function event(): string
    {
        return 'onboarding_started';
    }

    protected function actionLabelKey(): string
    {
        return 'onboarding.notifications.open';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        return ['count' => $this->onboarding->tasks()->where('assignee', 'employee')->count()];
    }

    /**
     * @return array{kind: string, onboarding_id: int, url: string}
     */
    public function payload(object $notifiable): array
    {
        return [
            'kind' => 'onboarding_started',
            'onboarding_id' => $this->onboarding->getKey(),
            'url' => route('portal.onboarding'),
        ];
    }
}
