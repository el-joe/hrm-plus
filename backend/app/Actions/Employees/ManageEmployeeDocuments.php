<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\Employees\EmployeeFileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * Employee documents on the private disk. Deleting is a soft delete: the
 * record and the file are kept (terminated employees keep their documents).
 */
class ManageEmployeeDocuments
{
    public function __construct(private readonly EmployeeFileStore $files) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(Employee $employee, array $data, UploadedFile $file): EmployeeDocument
    {
        return $employee->documents()->create([
            ...$this->attributes($data),
            ...$this->files->store($employee, $file, 'documents'),
            'uploaded_by' => Auth::id(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EmployeeDocument $document, array $data): EmployeeDocument
    {
        $document->update($this->attributes($data));

        return $document;
    }

    public function delete(EmployeeDocument $document): void
    {
        $document->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'category' => $data['category'],
            'title' => $data['title'],
            'issue_date' => $data['issue_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'visible_to_employee' => (bool) ($data['visible_to_employee'] ?? false),
            'notes' => $data['notes'] ?? null,
        ];
    }
}
