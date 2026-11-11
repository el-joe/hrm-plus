<?php

namespace App\Actions\Payroll;

use App\Enums\SalaryStructureScope;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assign a salary structure to an employee, job grade, department, branch
 * or the company from a date. The open assignment of the same scope is
 * closed the day before (never overwritten); an assignment cannot start on or
 * before the latest one of its scope. end() closes an assignment; a
 * future one that never took effect may be removed with withdraw().
 */
class AssignSalaryStructure
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws ValidationException
     */
    public function handle(SalaryStructure $structure, SalaryStructureScope $scope, ?int $scopeId, string $effectiveFrom, ?string $reason, ?User $user): SalaryStructureAssignment
    {
        $from = CarbonImmutable::parse($effectiveFrom)->startOfDay();
        $scopeId = $scope === SalaryStructureScope::Company ? null : $scopeId;

        if (! $structure->is_active) {
            throw ValidationException::withMessages(['salary_structure_id' => __('payroll.validation.structure_inactive')]);
        }

        return DB::transaction(function () use ($structure, $scope, $scopeId, $from, $reason, $user): SalaryStructureAssignment {
            $same = SalaryStructureAssignment::query()->where('scope_type', $scope)
                ->where(fn ($query) => $scopeId === null ? $query->whereNull('scope_id') : $query->where('scope_id', $scopeId))
                ->lockForUpdate()->orderByDesc('effective_from')->get();

            $latest = $same->first();

            if ($latest !== null && $from->lessThanOrEqualTo($latest->effective_from)) {
                throw ValidationException::withMessages(['effective_from' => __('payroll.validation.assignment_after', ['date' => $latest->effective_from->toDateString()])]);
            }

            if ($latest !== null && ($latest->effective_to === null || $latest->effective_to->greaterThanOrEqualTo($from))) {
                $latest->update(['effective_to' => $from->subDay()->toDateString()]);
            }

            $assignment = SalaryStructureAssignment::create([
                'salary_structure_id' => $structure->id,
                'scope_type' => $scope,
                'scope_id' => $scopeId,
                'effective_from' => $from->toDateString(),
                'reason' => $reason,
                'created_by' => $user?->id,
            ]);

            $this->audit->log('payroll.structure_assigned', $assignment, meta: ['structure' => $structure->code, 'scope' => $scope->value, 'scope_id' => $scopeId], actor: $user);

            return $assignment;
        });
    }

    /**
     * Stop an assignment after the given date (inclusive).
     *
     * @throws ValidationException
     */
    public function end(SalaryStructureAssignment $assignment, string $lastDay, ?User $user): SalaryStructureAssignment
    {
        $last = CarbonImmutable::parse($lastDay);

        if ($last->lessThan($assignment->effective_from)) {
            throw ValidationException::withMessages(['effective_to' => __('payroll.validation.end_before_start')]);
        }

        $assignment->update(['effective_to' => $last->toDateString()]);
        $this->audit->log('payroll.structure_assignment_ended', $assignment, meta: ['effective_to' => $last->toDateString()], actor: $user);

        return $assignment;
    }

    /**
     * Remove an assignment that has not taken effect yet (starts after today).
     *
     * @throws ValidationException
     */
    public function withdraw(SalaryStructureAssignment $assignment, ?User $user): void
    {
        if (! $assignment->effective_from->isFuture()) {
            throw ValidationException::withMessages(['assignment' => __('payroll.validation.assignment_in_force')]);
        }

        DB::transaction(function () use ($assignment, $user): void {
            $previous = SalaryStructureAssignment::query()->where('scope_type', $assignment->scope_type)
                ->where(fn ($query) => $assignment->scope_id === null ? $query->whereNull('scope_id') : $query->where('scope_id', $assignment->scope_id))
                ->whereKeyNot($assignment->id)
                ->where('effective_from', '<', $assignment->effective_from)
                ->orderByDesc('effective_from')->first();

            if ($previous !== null && $previous->effective_to?->toDateString() === $assignment->effective_from->subDay()->toDateString()) {
                $previous->update(['effective_to' => null]);
            }

            $this->audit->log('payroll.structure_assignment_withdrawn', $assignment, meta: ['structure_id' => $assignment->salary_structure_id], actor: $user);
            $assignment->delete();
        });
    }
}
