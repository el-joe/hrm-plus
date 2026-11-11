<?php

namespace App\Actions\Attendance;

use App\Models\AttendancePolicy;
use App\Models\AttendancePolicyVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Attendance policies and their rule versions. Versions are append-only: a
 * change of rules is a new version starting after the latest one, so every
 * day keeps being (re)calculated with the rules in force on that day.
 */
class SaveAttendancePolicy
{
    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $rules
     */
    public function create(array $identity, array $rules, string $effectiveFrom, User $user): AttendancePolicy
    {
        return DB::transaction(function () use ($identity, $rules, $effectiveFrom, $user): AttendancePolicy {
            $policy = AttendancePolicy::create($identity);
            $policy->versions()->create([...$rules, 'version' => 1, 'effective_from' => $effectiveFrom, 'created_by' => $user->id]);

            return $policy;
        });
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function addVersion(AttendancePolicy $policy, array $rules, string $effectiveFrom, User $user): AttendancePolicyVersion
    {
        return DB::transaction(function () use ($policy, $rules, $effectiveFrom, $user): AttendancePolicyVersion {
            $latest = $policy->versions()->lockForUpdate()->first();

            if ($latest !== null && $effectiveFrom <= $latest->effective_from->format('Y-m-d')) {
                throw ValidationException::withMessages(['effective_from' => __('schedules.policies.effective_after', ['date' => $latest->effective_from->format('Y-m-d')])]);
            }

            return $policy->versions()->create([
                ...$rules, 'version' => ($policy->versions()->max('version') ?? 0) + 1, 'effective_from' => $effectiveFrom, 'created_by' => $user->id,
            ]);
        });
    }
}
