<?php

namespace App\Actions\Leave;

use App\Models\LeavePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a leave policy, keeping one policy in force per leave
 * type and scope at any date. A new policy that starts after an open-ended
 * one of the same type and scope becomes its successor: the earlier policy
 * is closed the day before (effective-dated history, nothing overwritten).
 * Any other overlap is refused.
 */
class SaveLeavePolicy
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?LeavePolicy $policy = null): LeavePolicy
    {
        return DB::transaction(function () use ($data, $policy): LeavePolicy {
            $policy ??= new LeavePolicy;
            $policy->fill($data);

            $siblings = LeavePolicy::query()
                ->where('leave_type_id', $policy->leave_type_id)->where('scope_type', $policy->scope_type)
                ->where(fn ($query) => $policy->scope_id === null ? $query->whereNull('scope_id') : $query->where('scope_id', $policy->scope_id))
                ->when($policy->exists, fn ($query) => $query->whereKeyNot($policy->id))
                ->lockForUpdate()->get();

            $from = $policy->effective_from->format('Y-m-d');
            $to = $policy->effective_to?->format('Y-m-d');

            foreach ($siblings as $sibling) {
                $siblingFrom = $sibling->effective_from->format('Y-m-d');
                $siblingTo = $sibling->effective_to?->format('Y-m-d');
                $overlaps = $siblingFrom <= ($to ?? '9999-12-31') && ($siblingTo ?? '9999-12-31') >= $from;

                if (! $overlaps) {
                    continue;
                }

                if ($siblingTo === null && $siblingFrom < $from) {
                    $sibling->update(['effective_to' => CarbonImmutable::parse($from)->subDay()->format('Y-m-d')]);

                    continue;
                }

                throw ValidationException::withMessages(['effective_from' => __('leave.validation.policy_overlap', ['code' => $sibling->code])]);
            }

            $policy->save();

            return $policy;
        });
    }
}
