<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Services\Employees\EmployeeFileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Contracts: add, correct, renew (a new row linked to the renewed one) and
 * attach the signed copy (private disk). Contracts are never deleted.
 */
class ManageEmployeeContracts
{
    public function __construct(private readonly EmployeeFileStore $files) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Employee $employee, array $data, ?UploadedFile $file = null, ?EmployeeContract $renewalOf = null): EmployeeContract
    {
        return DB::transaction(function () use ($employee, $data, $file, $renewalOf): EmployeeContract {
            $contract = $employee->contracts()->create([
                ...$this->attributes($data),
                'renewal_of_id' => $renewalOf?->getKey(),
                'created_by' => Auth::id(),
                ...($file ? $this->files->store($employee, $file, 'contracts') : []),
            ]);

            return $contract;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EmployeeContract $contract, array $data, ?UploadedFile $file = null): EmployeeContract
    {
        return DB::transaction(function () use ($contract, $data, $file): EmployeeContract {
            $old = $contract->file_path;
            $contract->fill($this->attributes($data));

            if ($file !== null) {
                $contract->fill($this->files->store($contract->employee, $file, 'contracts'));
            }

            $contract->save();

            if ($file !== null && $old !== null) {
                $this->files->delete($old);
            }

            return $contract;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'reference' => $data['reference'] ?? null,
            'contract_type' => $data['contract_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
