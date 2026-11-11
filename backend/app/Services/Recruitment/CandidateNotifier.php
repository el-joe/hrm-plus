<?php

namespace App\Services\Recruitment;

use App\Models\Candidate;
use App\Notifications\Recruitment\CandidateMailNotification;
use Illuminate\Support\Facades\Notification;

/**
 * E-mails to candidates: only when recruitment.candidate_emails_enabled is on
 * and the candidate has an address; in the candidate's language.
 */
class CandidateNotifier
{
    public function send(Candidate $candidate, CandidateMailNotification $notification): bool
    {
        if (! setting('recruitment.candidate_emails_enabled') || blank($candidate->email)) {
            return false;
        }

        $locale = in_array($candidate->locale, ['ar', 'en'], true) ? $candidate->locale : (string) setting('localisation.default_locale');

        Notification::route('mail', $candidate->email)->notify($notification->locale($locale));

        return true;
    }
}
