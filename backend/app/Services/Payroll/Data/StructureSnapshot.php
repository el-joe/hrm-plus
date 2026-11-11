<?php

namespace App\Services\Payroll\Data;

use App\Enums\SalaryStructureScope;

/**
 * The salary structure that applies to an employee on a date: which
 * assignment matched (and at what scope), the structure version and its
 * component rules in display order.
 */
final readonly class StructureSnapshot
{
    /**
     * @param  list<ComponentRule>  $lines
     */
    public function __construct(
        public int $structureId,
        public string $code,
        public string $name,
        public int $versionId,
        public int $version,
        public string $versionFrom,
        public int $assignmentId,
        public SalaryStructureScope $scope,
        public array $lines,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'structure_id' => $this->structureId,
            'code' => $this->code,
            'name' => $this->name,
            'version_id' => $this->versionId,
            'version' => $this->version,
            'version_from' => $this->versionFrom,
            'assignment_id' => $this->assignmentId,
            'scope' => $this->scope->value,
            'lines' => array_map(fn (ComponentRule $line): array => $line->toArray(), $this->lines),
        ];
    }
}
