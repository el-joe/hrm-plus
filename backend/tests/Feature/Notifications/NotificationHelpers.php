<?php

use App\Models\DeviceToken;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\Automation\AutomationNotification;
use App\Services\Settings\SettingsService;

/*
| Helpers of the notification centre tests (Phase 8b).
*/

function employeeAccount(array $employee = [], array $user = []): User
{
    $record = Employee::factory()->create($employee);

    return User::factory()->employee()->create(['employee_id' => $record->id, ...$user]);
}

function greeting(string $name = 'Sara'): AutomationNotification
{
    return new AutomationNotification('birthday_greeting', ['employee' => ['ar' => 'سارة', 'en' => $name], 'company' => 'Horizon'], ['url' => '/portal']);
}

function registerDevice(User $user, string $platform = 'android'): DeviceToken
{
    $token = 'tok-'.bin2hex(random_bytes(20));

    return DeviceToken::query()->create([
        'user_id' => $user->id, 'token' => $token, 'token_hash' => DeviceToken::hashOf($token), 'platform' => $platform, 'last_seen_at' => now(),
    ]);
}

function setSetting(string $key, mixed $value): void
{
    app(SettingsService::class)->set($key, $value);
}
