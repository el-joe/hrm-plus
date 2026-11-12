<?php

namespace App\Http\Resources\V1;

use App\Models\Employee;
use App\Support\SensitiveValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in employee's own profile ("My Profile" of the mobile app).
 * Identity numbers are masked; no salary data.
 *
 * @mixin Employee
 */
class EmployeeProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $named = fn ($model): ?array => $model ? ['id' => $model->id, 'code' => $model->code ?? null, 'name' => $model->name] : null;

        return [
            'id' => $this->id,
            'employee_number' => $this->employee_number,
            'name' => $this->name,
            'name_translations' => ['ar' => $this->name_ar, 'en' => $this->name_en],
            'has_photo' => $this->photo_path !== null,
            'photo_url' => $this->photo_path !== null ? route('api.v1.me.profile.photo') : null,
            'personal' => [
                'date_of_birth' => $this->date_of_birth?->toDateString(),
                'gender' => $this->gender?->value,
                'marital_status' => $this->marital_status?->value,
                'nationality' => $this->nationality,
                'national_id_masked' => SensitiveValue::mask($this->national_id),
                'passport_number_masked' => SensitiveValue::mask($this->passport_number),
                'passport_expiry_date' => $this->passport_expiry_date?->toDateString(),
            ],
            'contact' => [
                'email' => $this->email,
                'personal_email' => $this->personal_email,
                'phone' => $this->phone,
                'alternate_phone' => $this->alternate_phone,
                'address' => $this->address,
                'city' => $this->city,
                'country_code' => $this->country_code,
            ],
            'emergency_contacts' => $this->emergencyContacts->map(fn ($contact): array => [
                'name' => $contact->name,
                'relationship' => $contact->relationship,
                'phone' => $contact->phone,
                'alternate_phone' => $contact->alternate_phone,
            ])->values()->all(),
            'employment' => [
                'status' => $this->status->value,
                'status_label' => $this->status->label(),
                'hire_date' => $this->hire_date?->toDateString(),
                'original_hire_date' => $this->original_hire_date?->toDateString(),
                'probation_end_date' => $this->probation_end_date?->toDateString(),
                'branch' => $named($this->branch),
                'department' => $named($this->department),
                'team' => $named($this->team),
                'position' => $named($this->position),
                'job_title' => $named($this->jobTitle),
                'job_grade' => $named($this->jobGrade),
                'employment_type' => $named($this->employmentType),
                'work_location' => $this->workLocation ? ['id' => $this->workLocation->id, 'name' => $this->workLocation->name] : null,
                'manager' => $this->manager ? [
                    'id' => $this->manager->id,
                    'employee_number' => $this->manager->employee_number,
                    'name' => $this->manager->name,
                    'email' => $this->manager->email,
                    'phone' => $this->manager->phone,
                ] : null,
            ],
        ];
    }
}
