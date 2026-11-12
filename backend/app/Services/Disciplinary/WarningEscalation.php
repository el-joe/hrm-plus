<?php

namespace App\Services\Disciplinary;

use App\Enums\DisciplinaryActionType;
use App\Models\DisciplinaryAction;
use App\Models\Employee;
use App\Models\ViolationType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Progressive discipline: verbal (1) → written (2) → final (3) warning.
 *
 * - An active warning is an approved action with a warning level whose
 *   expires_on is today or later; expires_on = effective date + setting
 *   disciplinary.warning_validity_months.
 * - With active warnings the suggestion is one level above the highest
 *   (capped at the final warning); without, the violation type's default
 *   action (a gross violation may default straight to suspension).
 * - Proposing a warning below the suggested level needs a written
 *   justification (ManageDisciplinaryAction::propose).
 */
class WarningEscalation
{
    /**
     * @return Collection<int, DisciplinaryAction>
     */
    public function activeWarnings(Employee $employee, ?string $onDate = null): Collection
    {
        return DisciplinaryAction::query()->withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->activeWarnings($onDate)
            ->with(['disciplinaryCase' => fn ($query) => $query->withoutGlobalScopes()->with('violationType')])
            ->orderByDesc('warning_level')->orderByDesc('effective_date')
            ->get();
    }

    public function highestActiveLevel(Employee $employee, ?string $onDate = null): int
    {
        return (int) $this->activeWarnings($employee, $onDate)->max('warning_level');
    }

    /**
     * @return array{type: DisciplinaryActionType, level: ?int, escalated: bool, active: Collection<int, DisciplinaryAction>}
     */
    public function suggest(Employee $employee, ViolationType $violation, ?string $onDate = null): array
    {
        $active = $this->activeWarnings($employee, $onDate);
        $highest = (int) $active->max('warning_level');

        if ($highest > 0) {
            $type = DisciplinaryActionType::forLevel($highest + 1);
            $default = $violation->default_action;

            // A default stronger than any warning (suspension, termination, deduction) still wins.
            if (! $default->isWarning()) {
                return ['type' => $default, 'level' => $type->warningLevel(), 'escalated' => true, 'active' => $active];
            }

            if ($default->warningLevel() > $type->warningLevel()) {
                $type = $default;
            }

            return ['type' => $type, 'level' => $type->warningLevel(), 'escalated' => true, 'active' => $active];
        }

        return ['type' => $violation->default_action, 'level' => $violation->default_action->warningLevel(), 'escalated' => false, 'active' => $active];
    }

    /**
     * Whether proposing this type is below the suggestion (a justification is then required).
     */
    public function isBelowSuggestion(DisciplinaryActionType $proposed, ?int $suggestedLevel): bool
    {
        return $suggestedLevel !== null && $proposed->isWarning() && $proposed->warningLevel() < $suggestedLevel;
    }

    public function expiryFor(string $effectiveDate): string
    {
        return CarbonImmutable::parse($effectiveDate)->addMonthsNoOverflow((int) setting('disciplinary.warning_validity_months'))->toDateString();
    }
}
