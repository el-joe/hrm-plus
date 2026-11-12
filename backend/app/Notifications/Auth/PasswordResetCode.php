<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The one-time code of the mobile app's "forgot password" flow (App\Services\Auth\PasswordResetCodes).
 * Sent by e-mail only and immediately (not queued: the user is waiting on the OTP screen); never stored
 * in the notification centre. The code itself is never logged by the application.
 */
class PasswordResetCode extends Notification
{
    use Queueable;

    public function __construct(private readonly string $code, private readonly int $minutes) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('passwords.code.subject', ['app' => config('app.name')]))
            ->greeting(__('passwords.code.greeting', ['name' => $notifiable->name]))
            ->line(__('passwords.code.intro'))
            ->line('**'.$this->code.'**')
            ->line(__('passwords.code.expires', ['minutes' => $this->minutes]))
            ->line(__('passwords.code.ignore'));
    }
}
