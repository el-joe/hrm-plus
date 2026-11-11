<?php

namespace App\Actions\Recruitment;

use App\Enums\OfferStatus;
use App\Enums\StageCategory;
use App\Exceptions\ApprovalException;
use App\Models\ApprovalWorkflow;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\User;
use App\Notifications\Recruitment\OfferSent;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Services\Recruitment\CandidateNotifier;
use App\Services\Recruitment\Pipeline;
use App\Services\Recruitment\ReferenceNumbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Offer lifecycle:
 *
 *   draft ──submit──▶ pending_approval ──(approval engine)──▶ approved | rejected
 *     │  (approval not required: submit → approved directly)
 *   approved ──send──▶ sent ──▶ accepted | declined | expired (offers past expires_on)
 *   any open state ──withdraw──▶ withdrawn;  rejected → edit → draft again.
 *
 * Approval uses the generic engine (workflow "job_offer") when the setting
 * recruitment.offer_requires_approval is on and the workflow is active;
 * App\Listeners\Recruitment\SyncOfferApproval applies the outcome.
 * Sending moves the application to the first "offer" stage and e-mails the
 * candidate. An offer needs a salary before it can be submitted.
 */
class ManageOffer
{
    public function __construct(
        private readonly ReferenceNumbers $references,
        private readonly ApprovalService $approvals,
        private readonly CandidateNotifier $notifier,
        private readonly Pipeline $pipeline,
        private readonly MoveApplication $move,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(JobApplication $application, array $data, User $actor): JobOffer
    {
        if (! $application->isActive()) {
            throw ValidationException::withMessages(['start_date' => __('recruitment.validation.application_closed')]);
        }

        if ($application->offers()->get()->contains(fn (JobOffer $offer): bool => $offer->status->isOpen())) {
            throw ValidationException::withMessages(['start_date' => __('recruitment.validation.offer_open')]);
        }

        return DB::transaction(function () use ($application, $data, $actor): JobOffer {
            $offer = new JobOffer([...$data, 'job_application_id' => $application->getKey(), 'created_by' => $actor->getKey()]);
            $offer->forceFill([
                'reference' => $this->references->next('offer'),
                'status' => OfferStatus::Draft,
                'currency' => (string) setting('localisation.currency_code'),
            ])->save();

            return $offer;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(JobOffer $offer, array $data): JobOffer
    {
        $this->expect($offer, [OfferStatus::Draft, OfferStatus::Rejected]);

        $offer->fill($data);
        $offer->status = OfferStatus::Draft;
        $offer->save();

        return $offer;
    }

    public function requiresApproval(): bool
    {
        return (bool) setting('recruitment.offer_requires_approval')
            && ApprovalWorkflow::query()->where('key', JobOffer::WORKFLOW)->where('is_active', true)->exists();
    }

    public function submit(JobOffer $offer, User $actor): JobOffer
    {
        $this->expect($offer, [OfferStatus::Draft]);

        if ($offer->basic_salary === null) {
            throw ValidationException::withMessages(['basic_salary' => __('recruitment.validation.offer_needs_salary')]);
        }

        if (! $this->requiresApproval()) {
            return $this->transition($offer, OfferStatus::Approved, $actor);
        }

        try {
            DB::transaction(function () use ($offer, $actor): void {
                $this->approvals->submit($offer, JobOffer::WORKFLOW, $actor);
                $offer->forceFill(['status' => OfferStatus::PendingApproval])->save();
            });
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['offer' => $exception->getMessage()]);
        }

        return $offer;
    }

    /**
     * Called by the approval listener.
     */
    public function applyApprovalOutcome(JobOffer $offer, bool $approved): void
    {
        if ($offer->status === OfferStatus::PendingApproval) {
            $offer->forceFill(['status' => $approved ? OfferStatus::Approved : OfferStatus::Rejected])->save();
        }
    }

    public function send(JobOffer $offer, User $actor): JobOffer
    {
        $this->expect($offer, [OfferStatus::Approved]);

        if ($offer->expires_on->isPast() && ! $offer->expires_on->isToday()) {
            throw ValidationException::withMessages(['expires_on' => __('recruitment.validation.offer_expired')]);
        }

        $offer->forceFill(['status' => OfferStatus::Sent, 'sent_at' => now()])->save();
        $this->audit->log('recruitment.offer_sent', $offer, actor: $actor);

        $application = $offer->application()->with(['stage', 'vacancy', 'candidate'])->firstOrFail();

        if ($application->stage->category !== StageCategory::Offer
            && ($stage = $this->pipeline->firstOfCategory($application->vacancy, StageCategory::Offer)) !== null) {
            $this->move->handle($application, $stage, $actor, __('recruitment.history.offer_sent', ['reference' => $offer->reference]), notifyCandidate: false);
        }

        $this->notifier->send($application->candidate, new OfferSent($offer));

        return $offer;
    }

    public function respond(JobOffer $offer, bool $accepted, User $actor, ?string $note = null): JobOffer
    {
        $this->expect($offer, [OfferStatus::Sent]);

        return $this->transition($offer, $accepted ? OfferStatus::Accepted : OfferStatus::Declined, $actor, $note);
    }

    public function withdraw(JobOffer $offer, User $actor, ?string $note = null): JobOffer
    {
        $this->expect($offer, [OfferStatus::Draft, OfferStatus::PendingApproval, OfferStatus::Approved, OfferStatus::Rejected, OfferStatus::Sent]);

        if ($offer->status === OfferStatus::PendingApproval && ($request = $offer->latestApprovalRequest) && $request->isPending()) {
            try {
                $this->approvals->cancel($request, $actor, $note);
            } catch (ApprovalException) {
                // Someone else submitted it: the request stays, the offer is withdrawn regardless.
            }
        }

        return $this->transition($offer, OfferStatus::Withdrawn, $actor, $note);
    }

    /**
     * Sent offers past their expiry date. Run daily by recruitment:expire-offers.
     */
    public function expireDue(): int
    {
        return JobOffer::query()->withoutGlobalScopes()
            ->where('status', OfferStatus::Sent)
            ->whereDate('expires_on', '<', today())
            ->get()
            ->each(fn (JobOffer $offer) => $offer->forceFill(['status' => OfferStatus::Expired])->save())
            ->count();
    }

    private function transition(JobOffer $offer, OfferStatus $status, User $actor, ?string $note = null): JobOffer
    {
        $responded = in_array($status, [OfferStatus::Accepted, OfferStatus::Declined], true);

        $offer->forceFill([
            'status' => $status,
            'responded_at' => $responded ? now() : $offer->responded_at,
            'response_note' => $note ?? $offer->response_note,
        ])->save();

        $this->audit->log('recruitment.offer_'.$status->value, $offer, actor: $actor);

        return $offer;
    }

    /**
     * @param  list<OfferStatus>  $allowed
     */
    private function expect(JobOffer $offer, array $allowed): void
    {
        if (! in_array($offer->status, $allowed, true)) {
            throw ValidationException::withMessages(['offer' => __('recruitment.validation.offer_wrong_status', ['status' => $offer->status->label()])]);
        }
    }
}
