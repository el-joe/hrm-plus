{{--
    Employee create / edit form sections. The placement section is shown on create only; afterwards
    the placement changes through dated history entries (transfer, promotion, ...).
    Variables: $employee, $options (EmployeeFormOptions::form).
--}}
@php
    $creating = ! $employee->exists;
    $canEditSensitive = $creating ? auth()->user()->can('employee_sensitive.update') : auth()->user()->can('updateSensitive', $employee);
    $contacts = old('emergency_contacts', $employee->exists ? $employee->emergencyContacts->map->only(['name', 'relationship', 'phone', 'alternate_phone'])->all() : []);
    $groupIds = array_map('intval', (array) old('groups', $employee->exists ? $employee->groups->pluck('id')->all() : []));
@endphp

<x-form.section :title="__('employees.form.identity')" :description="__('employees.form.identity_help')" data-section="identity">
    <x-form.input name="name_ar" :label="__('employees.fields.name_ar')" :value="old('name_ar', $employee->name_ar)" required maxlength="150" lang="ar" dir="rtl" />
    <x-form.input name="name_en" :label="__('employees.fields.name_en')" :value="old('name_en', $employee->name_en)" required maxlength="150" lang="en" dir="ltr" />
    <x-form.input name="employee_number" :label="__('employees.fields.employee_number')" :value="old('employee_number', $employee->employee_number)" maxlength="30" dir="ltr"
        :help="$creating ? __('employees.form.number_help') : null" :required="! $creating" />
    <x-form.input name="date_of_birth" type="date" :label="__('employees.fields.date_of_birth')" :value="old('date_of_birth', $employee->date_of_birth?->toDateString())" />
    <x-form.select name="gender" :label="__('employees.fields.gender')">
        <option value="">{{ __('structure.common.not_set') }}</option>
        @foreach ($options['genders'] as $gender)
            <option value="{{ $gender->value }}" @selected(old('gender', $employee->gender?->value) === $gender->value)>{{ $gender->label() }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="marital_status" :label="__('employees.fields.marital_status')">
        <option value="">{{ __('structure.common.not_set') }}</option>
        @foreach ($options['maritalStatuses'] as $marital)
            <option value="{{ $marital->value }}" @selected(old('marital_status', $employee->marital_status?->value) === $marital->value)>{{ $marital->label() }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="nationality" :label="__('employees.fields.nationality')" :value="old('nationality', $employee->nationality)" maxlength="2" dir="ltr"
        :help="__('employees.form.country_help')" class="uppercase" />
    @if ($canEditSensitive)
        <x-form.input name="national_id" :label="__('employees.fields.national_id')" maxlength="30" dir="ltr" autocomplete="off"
            :value="old('national_id', $employee->national_id)" :help="__('employees.form.sensitive_help')" />
        <x-form.input name="passport_number" :label="__('employees.fields.passport_number')" maxlength="20" dir="ltr" autocomplete="off"
            :value="old('passport_number', $employee->passport_number)" />
    @endif
    <x-form.input name="passport_expiry_date" type="date" :label="__('employees.fields.passport_expiry_date')" :value="old('passport_expiry_date', $employee->passport_expiry_date?->toDateString())" />
    <div class="md:col-span-2">
        <x-form.file-input name="photo" :label="__('employees.fields.photo')" accept="image/jpeg,image/png,image/webp" :help="__('employees.form.photo_help', ['kb' => config('employees.photo_max_kb')])" />
        @if ($employee->photo_path)
            <div class="mt-2">
                <input type="hidden" name="remove_photo" value="0">
                <x-form.checkbox name="remove_photo" :label="__('employees.form.remove_photo')" :checked="(bool) old('remove_photo')" />
            </div>
        @endif
    </div>
</x-form.section>

<x-form.section :title="__('employees.form.contact')" :description="__('employees.form.contact_help')" data-section="contact">
    <x-form.input name="email" type="email" :label="__('employees.fields.email')" :value="old('email', $employee->email)" maxlength="255" dir="ltr" />
    <x-form.input name="personal_email" type="email" :label="__('employees.fields.personal_email')" :value="old('personal_email', $employee->personal_email)" maxlength="255" dir="ltr" />
    <x-form.input name="phone" type="tel" :label="__('employees.fields.phone')" :value="old('phone', $employee->phone)" maxlength="30" dir="ltr" />
    <x-form.input name="alternate_phone" type="tel" :label="__('employees.fields.alternate_phone')" :value="old('alternate_phone', $employee->alternate_phone)" maxlength="30" dir="ltr" />
    <div class="md:col-span-2">
        <x-form.input name="address" :label="__('employees.fields.address')" :value="old('address', $employee->address)" maxlength="500" />
    </div>
    <x-form.input name="city" :label="__('employees.fields.city')" :value="old('city', $employee->city)" maxlength="100" />
    <x-form.input name="country_code" :label="__('employees.fields.country_code')" :value="old('country_code', $employee->country_code)" maxlength="2" dir="ltr" class="uppercase" />
</x-form.section>

<x-form.section :title="__('employees.form.emergency')" :description="__('employees.form.emergency_help')" data-section="emergency">
    @for ($index = 0; $index < 2; $index++)
        <fieldset class="grid gap-x-6 gap-y-5 md:col-span-2 md:grid-cols-2 {{ $index ? 'border-t border-border-subtle pt-5' : '' }}">
            <legend class="sr-only">{{ __('employees.form.contact_n', ['n' => $index + 1]) }}</legend>
            <x-form.input :name="'emergency_contacts['.$index.'][name]'" :label="__('employees.fields.contact_name')" :value="$contacts[$index]['name'] ?? ''" maxlength="150" />
            <x-form.input :name="'emergency_contacts['.$index.'][relationship]'" :label="__('employees.fields.relationship')" :value="$contacts[$index]['relationship'] ?? ''" maxlength="50" />
            <x-form.input :name="'emergency_contacts['.$index.'][phone]'" type="tel" :label="__('employees.fields.phone')" :value="$contacts[$index]['phone'] ?? ''" maxlength="30" dir="ltr" />
            <x-form.input :name="'emergency_contacts['.$index.'][alternate_phone]'" type="tel" :label="__('employees.fields.alternate_phone')" :value="$contacts[$index]['alternate_phone'] ?? ''" maxlength="30" dir="ltr" />
        </fieldset>
    @endfor
</x-form.section>

@if ($creating)
    <x-form.section :title="__('employees.form.employment')" :description="__('employees.form.employment_help')" data-section="employment">
        <x-form.input name="hire_date" type="date" :label="__('employees.fields.hire_date')" :value="old('hire_date', now()->toDateString())" required />
        <x-form.input name="probation_end_date" type="date" :label="__('employees.fields.probation_end_date')" :value="old('probation_end_date')" />
        @include('admin.employees._placement-fields', ['values' => []])
        @can('employee_financials.update')
            <x-form.input name="basic_salary" type="number" min="0" step="0.01" inputmode="decimal" dir="ltr"
                :label="__('employees.fields.basic_salary').' ('.setting('localisation.currency_code').')'" :value="old('basic_salary')" />
        @endcan
    </x-form.section>
@else
    <x-form.section :title="__('employees.form.employment')" :description="__('employees.form.employment_locked')" data-section="employment">
        <x-form.input name="probation_end_date" type="date" :label="__('employees.fields.probation_end_date')" :value="old('probation_end_date', $employee->probation_end_date?->toDateString())" />
    </x-form.section>
@endif

@if ($options['groups']->isNotEmpty())
    <x-form.section :title="__('employees.fields.groups')" :description="__('employees.form.groups_help')" data-section="groups">
        <fieldset class="md:col-span-2">
            <legend class="sr-only">{{ __('employees.fields.groups') }}</legend>
            <input type="hidden" name="groups[]" value="">
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($options['groups'] as $group)
                    <x-form.checkbox name="groups[]" :id="'group-'.$group->id" :value="$group->id" :label="$group->code.' · '.$group->name"
                        :checked="in_array($group->id, $groupIds, true)" />
                @endforeach
            </div>
            @error('groups.*') <p class="mt-2 text-caption-md text-feedback-danger-text" data-field-error>{{ $message }}</p> @enderror
        </fieldset>
    </x-form.section>
@endif

@if ($options['customFields']->isNotEmpty())
    <x-form.section :title="__('employees.form.custom_fields')" :description="__('employees.form.custom_fields_help')" data-section="custom-fields">
        @foreach ($options['customFields'] as $field)
            @php
                $name = 'custom_fields['.$field->code.']';
                $current = old('custom_fields.'.$field->code, $options['customValues'][$field->code] ?? null);
            @endphp
            @switch($field->type)
                @case(App\Enums\CustomFieldType::Textarea)
                    <div class="md:col-span-2">
                        <x-form.textarea :name="$name" :label="$field->name" :required="$field->is_required" maxlength="2000" rows="3">{{ $current }}</x-form.textarea>
                    </div>
                    @break
                @case(App\Enums\CustomFieldType::Select)
                    <x-form.select :name="$name" :label="$field->name" :required="$field->is_required">
                        <option value="">—</option>
                        @foreach ($field->options as $option)
                            <option value="{{ $option->value }}" @selected((string) $current === $option->value)>{{ $option->label() }}</option>
                        @endforeach
                    </x-form.select>
                    @break
                @case(App\Enums\CustomFieldType::Boolean)
                    <div class="md:self-end">
                        <input type="hidden" name="{{ $name }}" value="0">
                        <x-form.switch :name="$name" :id="'cf-'.$field->code" :label="$field->name" :checked="(bool) $current" />
                    </div>
                    @break
                @default
                    <x-form.input :name="$name" :label="$field->name" :required="$field->is_required"
                        :type="match ($field->type) { App\Enums\CustomFieldType::Number => 'number', App\Enums\CustomFieldType::Date => 'date', default => 'text' }"
                        :step="$field->type === App\Enums\CustomFieldType::Number ? 'any' : null" :value="$current" />
            @endswitch
        @endforeach
    </x-form.section>
@endif
