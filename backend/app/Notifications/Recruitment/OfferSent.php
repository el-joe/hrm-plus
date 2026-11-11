<?php

namespace App\Notifications\Recruitment;

use App\Models\JobOffer;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The offer letter summary to the candidate (their own offer, so it includes the salary).
 */
class OfferSent extends CandidateMailNotification
{
    public function __construct(public readonly JobOffer $offer) {}

    public function toMail(object $notifiable): MailMessage
    {
        $offer = $this->offer->loadMissing(['application.candidate', 'application.vacancy', 'jobTitle']);
        $title = $offer->jobTitle?->name ?? $offer->application->vacancy->title;

        $mail = (new MailMessage)
            ->subject(__('recruitment.mail.offer_subject', ['title' => $title]))
            ->greeting(__('recruitment.mail.greeting', ['name' => $offer->application->candidate->name]))
            ->line(__('recruitment.mail.offer_line', ['title' => $title, 'company' => config('app.name')]))
            ->line(__('recruitment.mail.offer_start', ['date' => $offer->start_date->toDateString()]));

        if ($offer->basic_salary !== null) {
            $mail->line(__('recruitment.mail.offer_salary', ['amount' => Money::format($offer->basic_salary), 'currency' => $offer->currency]));
        }

        return $mail->line(__('recruitment.mail.offer_expiry', ['date' => $offer->expires_on->toDateString(), 'reference' => $offer->reference]));
    }
}
