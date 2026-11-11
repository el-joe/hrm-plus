<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Services\Employees\CustomFieldValues;
use App\Services\Employees\EmployeeFileStore;
use Illuminate\Http\UploadedFile;

/**
 * The parts of an employee saved alongside the main record on create and
 * edit: emergency contacts, group memberships, custom field values and the
 * profile photo. Each key is only touched when present in the data.
 */
class SyncEmployeeDetails
{
    public function __construct(
        private readonly CustomFieldValues $customFields,
        private readonly EmployeeFileStore $files,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Employee $employee, array $data, ?UploadedFile $photo = null): void
    {
        if (array_key_exists('emergency_contacts', $data)) {
            $employee->emergencyContacts()->get()->each->delete();

            foreach (array_values(array_filter((array) $data['emergency_contacts'], fn ($contact): bool => filled($contact['name'] ?? null))) as $index => $contact) {
                $employee->emergencyContacts()->create([
                    'name' => $contact['name'],
                    'relationship' => $contact['relationship'],
                    'phone' => $contact['phone'],
                    'alternate_phone' => $contact['alternate_phone'] ?? null,
                    'sort_order' => $index,
                ]);
            }
        }

        if (array_key_exists('groups', $data)) {
            $employee->groups()->sync(collect((array) $data['groups'])->filter()->mapWithKeys(fn ($id): array => [(int) $id => ['created_at' => now()]])->all());
        }

        if (array_key_exists('custom_fields', $data)) {
            $this->customFields->save($employee, (array) $data['custom_fields']);
        }

        if ($photo !== null) {
            $old = $employee->photo_path;
            $employee->photo_path = $this->files->store($employee, $photo, 'photo')['file_path'];
            $employee->save();
            $this->files->delete($old);
        } elseif (! empty($data['remove_photo']) && $employee->photo_path !== null) {
            $this->files->delete($employee->photo_path);
            $employee->photo_path = null;
            $employee->save();
        }
    }
}
