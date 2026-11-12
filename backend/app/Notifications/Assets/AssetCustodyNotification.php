<?php

namespace App\Notifications\Assets;

use App\Models\AssetAssignment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Custody events to the employee, database + mail (mail when setting notifications.email_enabled):
 * assigned (please acknowledge receipt) and returned (confirmation with the condition recorded).
 */
class AssetCustodyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const KINDS = ['assigned', 'returned'];

    public function __construct(public readonly AssetAssignment $assignment, public readonly string $kind) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return setting('notifications.email_enabled') ? ['database', 'mail'] : ['database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)->subject($data['title'])->line($data['message'])
            ->action(__('assets.notifications.open', [], $notifiable->locale), $data['url']);
    }

    /**
     * @return array{kind: string, asset_assignment_id: int, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : null;
        $assignment = $this->assignment->loadMissing(['asset' => fn ($query) => $query->withoutGlobalScopes()]);

        return [
            'kind' => 'asset_'.$this->kind,
            'asset_assignment_id' => $assignment->id,
            'title' => __('assets.notifications.'.$this->kind.'_title', [], $locale),
            'message' => __('assets.notifications.'.$this->kind.'_message', [
                'asset' => $assignment->asset->label(),
                'date' => ($this->kind === 'returned' ? $assignment->returned_on : $assignment->assigned_on)?->toDateString(),
                'condition' => __('assets.enums.conditions.'.($assignment->condition_on_return ?? $assignment->condition_on_issue)->value, [], $locale),
            ], $locale),
            'url' => route('portal.assets'),
        ];
    }
}
