<?php

namespace App\Actions\Payroll;

use App\Enums\StatutoryType;
use App\Models\StatutoryRuleSet;
use App\Models\StatutoryRuleVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Add a version to a statutory rule table. Versions are never edited: the
 * new one must start after the latest and closes it the day before.
 * Social insurance needs the employee rate (employer rate, floor, ceiling
 * optional, floor ≤ ceiling); income tax needs contiguous brackets starting
 * at 0, each upper bound above its lower bound, only the last one open.
 */
class SaveStatutoryRuleVersion
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{effective_from: string, employee_rate?: ?string, employer_rate?: ?string, salary_floor?: ?string, salary_ceiling?: ?string,
     *               annual_exemption?: ?string, notes?: ?string, brackets?: list<array{lower_bound?: ?string, upper_bound?: ?string, rate?: ?string}>}  $data
     *
     * @throws ValidationException
     */
    public function handle(StatutoryRuleSet $set, array $data, ?User $user): StatutoryRuleVersion
    {
        $from = CarbonImmutable::parse($data['effective_from'])->startOfDay();
        $values = $set->type === StatutoryType::SocialInsurance ? $this->insurance($data) : $this->tax($data);

        return DB::transaction(function () use ($set, $from, $values, $data, $user): StatutoryRuleVersion {
            StatutoryRuleSet::query()->whereKey($set->id)->lockForUpdate()->first();
            $latest = $set->versions()->first();

            if ($latest !== null && $from->lessThanOrEqualTo($latest->effective_from)) {
                throw ValidationException::withMessages(['effective_from' => __('payroll.validation.version_after', ['date' => $latest->effective_from->toDateString()])]);
            }

            if ($latest !== null && ($latest->effective_to === null || $latest->effective_to->greaterThanOrEqualTo($from))) {
                $latest->update(['effective_to' => $from->subDay()->toDateString()]);
            }

            $version = $set->versions()->create([
                ...$values['version'],
                'version' => ($latest?->version ?? 0) + 1,
                'effective_from' => $from->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            foreach ($values['brackets'] as $index => $bracket) {
                $version->brackets()->create([...$bracket, 'sort_order' => $index + 1]);
            }

            $this->audit->log('payroll.statutory_version_created', $version, meta: ['set' => $set->code, 'version' => $version->version, 'demo' => $set->is_demo], actor: $user);

            return $version;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{version: array<string, ?string>, brackets: list<array<string, ?string>>}
     */
    private function insurance(array $data): array
    {
        if (! filled($data['employee_rate'] ?? null)) {
            throw ValidationException::withMessages(['employee_rate' => __('payroll.validation.rate_required')]);
        }

        $floor = filled($data['salary_floor'] ?? null) ? (string) $data['salary_floor'] : null;
        $ceiling = filled($data['salary_ceiling'] ?? null) ? (string) $data['salary_ceiling'] : null;

        if ($floor !== null && $ceiling !== null && bccomp($floor, $ceiling, 2) > 0) {
            throw ValidationException::withMessages(['salary_ceiling' => __('payroll.validation.ceiling_below_floor')]);
        }

        return ['version' => [
            'employee_rate' => (string) $data['employee_rate'],
            'employer_rate' => filled($data['employer_rate'] ?? null) ? (string) $data['employer_rate'] : null,
            'salary_floor' => $floor,
            'salary_ceiling' => $ceiling,
        ], 'brackets' => []];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{version: array<string, ?string>, brackets: list<array<string, ?string>>}
     */
    private function tax(array $data): array
    {
        $rows = array_values(array_filter($data['brackets'] ?? [], fn (array $row): bool => filled($row['rate'] ?? null) || filled($row['lower_bound'] ?? null)));

        if ($rows === []) {
            throw ValidationException::withMessages(['brackets' => __('payroll.validation.brackets_required')]);
        }

        usort($rows, fn (array $a, array $b): int => bccomp((string) ($a['lower_bound'] ?? '0'), (string) ($b['lower_bound'] ?? '0'), 2));
        $expected = '0.00';
        $brackets = [];

        foreach ($rows as $index => $row) {
            $lower = bcadd((string) ($row['lower_bound'] ?? '0'), '0', 2);
            $upper = filled($row['upper_bound'] ?? null) ? bcadd((string) $row['upper_bound'], '0', 2) : null;
            $last = $index === count($rows) - 1;

            if (bccomp($lower, $expected, 2) !== 0 || ($upper !== null && bccomp($upper, $lower, 2) <= 0) || ($upper === null && ! $last) || ! filled($row['rate'] ?? null)) {
                throw ValidationException::withMessages(['brackets' => __('payroll.validation.brackets_contiguous')]);
            }

            $brackets[] = ['lower_bound' => $lower, 'upper_bound' => $upper, 'rate' => (string) $row['rate']];
            $expected = $upper ?? $expected;
        }

        return ['version' => ['annual_exemption' => filled($data['annual_exemption'] ?? null) ? (string) $data['annual_exemption'] : null], 'brackets' => $brackets];
    }
}
