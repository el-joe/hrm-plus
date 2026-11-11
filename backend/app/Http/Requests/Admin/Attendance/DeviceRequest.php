<?php

namespace App\Http\Requests\Admin\Attendance;

use App\Models\AttendanceDevice;
use App\Models\Branch;
use App\Models\BranchLocation;
use App\Rules\ExistsInScope;
use App\Services\Attendance\Devices\DeviceAdapterRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create / edit an attendance device. Authorisation: the routes carry the
 * permission, the controller the record policy.
 */
class DeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var AttendanceDevice|null $device */
        $device = $this->route('attendance_device');
        $adapters = app(DeviceAdapterRegistry::class);
        $restricted = $this->user()->hasDataRestrictions();

        return [
            'name' => ['required', 'string', 'max:150'],
            'serial_number' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique(AttendanceDevice::class)->ignore($device?->id)],
            'branch_id' => [$restricted ? 'required' : 'nullable', 'integer', new ExistsInScope(Branch::class, keep: $device?->branch_id)],
            'branch_location_id' => ['nullable', 'integer', Rule::exists(BranchLocation::class, 'id')->where('branch_id', $this->integer('branch_id'))],
            'adapter' => ['required', Rule::in($adapters->keys())],
            'connection_mode' => ['required', Rule::in($adapters->modes((string) $this->input('adapter')))],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'credentials' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'timezone:all'],
            'pull_interval_minutes' => ['required', 'integer', 'between:1,1440'],
            'simulate_failure' => ['sometimes', 'boolean'],
            'unknown_user_id' => ['nullable', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $simulated = app(DeviceAdapterRegistry::class)->isSimulated((string) $this->input('adapter'));

            if (! $simulated && $this->input('connection_mode') === 'pull' && ! $this->filled('host')) {
                $validator->errors()->add('host', __('devices.form.host_required'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('devices.fields');
    }
}
