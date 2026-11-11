<?php

namespace App\Notifications\Recruitment;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base of the e-mails sent to candidates (who have no account): delivered to
 * an on-demand mail route by App\Services\Recruitment\CandidateNotifier, in
 * the candidate's language, through the queue.
 */
abstract class CandidateMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
