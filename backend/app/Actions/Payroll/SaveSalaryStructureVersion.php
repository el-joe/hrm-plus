<?php

namespace App\Actions\Payroll;

use App\Enums\PayCalculationType;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create a salary structure (with version 1) or add a new version to one.
 *
 * A version is never edited: the new one starts after the latest version's
 * start date and closes it the day before (effective_to), so the history of
 * what applied when is kept for payroll audit. Lines may only use components
 * allowed on structures (not "variable"), at most one basic component, and a
 * percentage line must be based on the basic salary or on another line of the
 * same version, without cycles.
 */
class SaveSalaryStructureVersion
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{code: string, name_ar: string, name_en: string, description?: ?string, is_active?: bool}  $structureData
     * @param  array{effective_from: string, notes?: ?string, lines: list<array<string, mixed>>}  $versionData
     */
    public function create(array $structureData, array $versionData, ?User $user): SalaryStructure
    {
        return DB::transaction(function () use ($structureData, $versionData, $user): SalaryStructure {
            $structure = SalaryStructure::create($structureData);
            $this->addVersion($structure, $versionData, $user);

            return $structure;
        });
    }

    /**
     * @param  array{effective_from: string, notes?: ?string, lines: list<array<string, mixed>>}  $data
     *
     * @throws ValidationException
     */
    public function addVersion(SalaryStructure $structure, array $data, ?User $user): SalaryStructureVersion
    {
        $lines = $this->validatedLines($data['lines'] ?? []);
        $from = CarbonImmutable::parse($data['effective_from'])->startOfDay();

        return DB::transaction(function () use ($structure, $data, $lines, $from, $user): SalaryStructureVersion {
            SalaryStructure::query()->whereKey($structure->id)->lockForUpdate()->first();
            $latest = $structure->versions()->first();

            if ($latest !== null && $from->lessThanOrEqualTo($latest->effective_from)) {
                throw ValidationException::withMessages(['effective_from' => __('payroll.validation.version_after', ['date' => $latest->effective_from->toDateString()])]);
            }

            if ($latest !== null && ($latest->effective_to === null || $latest->effective_to->greaterThanOrEqualTo($from))) {
                $latest->update(['effective_to' => $from->subDay()->toDateString()]);
            }

            $version = $structure->versions()->create([
                'version' => ($latest?->version ?? 0) + 1,
                'effective_from' => $from->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            foreach ($lines as $index => $line) {
                $version->lines()->create([...$line, 'sort_order' => $line['sort_order'] ?? ($index + 1) * 10]);
            }

            $this->audit->log('payroll.structure_version_created', $version, meta: ['structure' => $structure->code, 'version' => $version->version, 'lines' => count($lines)], actor: $user);

            return $version;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{salary_component_id: int, amount: ?string, rate: ?string, base_component_id: ?int, sort_order: ?int}>
     *
     * @throws ValidationException
     */
    public function validatedLines(array $lines): array
    {
        $lines = array_values(array_filter($lines, fn (array $line): bool => filled($line['salary_component_id'] ?? null)));

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('payroll.validation.lines_required')]);
        }

        $components = SalaryComponent::query()->whereKey(array_column($lines, 'salary_component_id'))->get()->keyBy('id');
        $ids = array_map('intval', array_column($lines, 'salary_component_id'));

        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['lines' => __('payroll.validation.duplicate_line')]);
        }

        $clean = [];
        $bases = [];
        $basicCount = 0;

        foreach ($lines as $index => $line) {
            $component = $components->get((int) $line['salary_component_id']);

            if ($component === null || ! $component->calculation_type->allowsStructureLines()) {
                throw ValidationException::withMessages(["lines.{$index}.salary_component_id" => __('payroll.validation.line_component')]);
            }

            $basicCount += $component->calculation_type === PayCalculationType::Basic ? 1 : 0;
            $type = $component->calculation_type;
            $base = $type === PayCalculationType::Percentage
                ? (filled($line['base_component_id'] ?? null) ? (int) $line['base_component_id'] : $component->base_component_id)
                : null;

            if ($base !== null && ! in_array($base, $ids, true)) {
                throw ValidationException::withMessages(["lines.{$index}.base_component_id" => __('payroll.validation.base_not_in_structure')]);
            }

            $bases[$component->id] = $base;
            $clean[] = [
                'salary_component_id' => $component->id,
                'amount' => in_array($type, [PayCalculationType::Basic, PayCalculationType::Percentage, PayCalculationType::Statutory], true) || ! filled($line['amount'] ?? null) ? null : (string) $line['amount'],
                'rate' => ($type === PayCalculationType::Percentage || $type->isQuantityBased()) && filled($line['rate'] ?? null) ? (string) $line['rate'] : null,
                'base_component_id' => $type === PayCalculationType::Percentage && filled($line['base_component_id'] ?? null) ? (int) $line['base_component_id'] : null,
                'sort_order' => filled($line['sort_order'] ?? null) ? (int) $line['sort_order'] : null,
            ];
        }

        if ($basicCount > 1) {
            throw ValidationException::withMessages(['lines' => __('payroll.validation.one_basic')]);
        }

        foreach (array_keys($bases) as $id) {
            $seen = [];
            $current = $id;

            while (($bases[$current] ?? null) !== null) {
                $current = $bases[$current];

                if ($current === $id || in_array($current, $seen, true)) {
                    throw ValidationException::withMessages(['lines' => __('payroll.validation.base_cycle')]);
                }

                $seen[] = $current;
            }
        }

        return $clean;
    }
}
