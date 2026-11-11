<?php

namespace App\Http\Resources\V1;

use App\Models\EmployeeLoan;
use App\Models\LoanInstalment;
use App\Models\LoanTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An employee's own loan or advance (mobile app). Money is a DECIMAL string.
 * "schedule", "ledger" and "approval" are included when loaded (detail endpoint).
 *
 * @mixin EmployeeLoan
 */
class EmployeeLoanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'requested_amount' => (string) $this->requested_amount,
            'requested_instalments' => $this->requested_instalments,
            'requested_start' => $this->requested_start->format('Y-m'),
            'approved_amount' => $this->approved_amount === null ? null : (string) $this->approved_amount,
            'instalment_count' => $this->instalment_count,
            'start_month' => $this->start_date?->format('Y-m'),
            'outstanding_amount' => (string) $this->outstanding_amount,
            'repaid_amount' => $this->repaidAmount(),
            'currency' => (string) setting('localisation.currency_code'),
            'reason' => $this->reason,
            'rejection_reason' => $this->rejection_reason,
            'current_step' => $this->whenLoaded('latestApprovalRequest', fn () => $this->latestApprovalRequest?->isPending() ? $this->latestApprovalRequest->currentStep?->name : null),
            'can_cancel' => $this->isPending(),
            'schedule' => $this->whenLoaded('instalments', fn () => $this->instalments->map(fn (LoanInstalment $row): array => [
                'id' => $row->id, 'sequence' => $row->sequence, 'due_date' => $row->due_date->toDateString(), 'amount' => (string) $row->amount,
                'paid_amount' => (string) $row->paid_amount, 'status' => $row->status->value, 'status_label' => $row->status->label(),
                'final_settlement' => $row->is_final_settlement,
            ])->all()),
            'ledger' => $this->whenLoaded('transactions', fn () => $this->transactions->map(fn (LoanTransaction $row): array => [
                'type' => $row->type->value, 'type_label' => $row->type->label(), 'amount' => (string) $row->amount,
                'balance_after' => (string) $row->balance_after, 'date' => $row->occurred_on->toDateString(),
            ])->all()),
            'approval' => $this->whenLoaded('approvalRequests', fn () => $this->approvalRequests->flatMap->actions->map(fn ($action): array => [
                'action' => $action->action->value, 'step' => $action->step_name, 'reason' => $action->reason, 'at' => $action->created_at?->toIso8601ZuluString(),
            ])->values()->all()),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'decided_at' => $this->decided_at?->toIso8601ZuluString(),
        ];
    }
}
