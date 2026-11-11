{{--
    Fields shared by the create and edit screens.
    Variables: $admin, $roles, $assignedRoleIds, $statuses, $scopeTypes, $scopes, $locales.
--}}
<x-form.section :title="__('app.admin_users.details')" :description="__('app.admin_users.details_help')" data-section="details">
    <x-form.input name="name" :label="__('app.common.name')" :value="old('name', $admin->name)" required maxlength="255" />

    <x-form.input name="email" type="email" :label="__('app.common.email')" :value="old('email', $admin->email)" required maxlength="255" />

    <x-form.input name="password" type="password" :label="__('app.common.password')" autocomplete="new-password"
        :required="! $admin->exists" :help="$admin->exists ? __('app.admin_users.password_help_edit') : null" />

    <x-form.input name="password_confirmation" type="password" :label="__('app.common.password_confirmation')" autocomplete="new-password"
        data-match="password" />

    <x-form.select name="status" :label="__('app.common.status')" required>
        @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected(old('status', $admin->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
        @endforeach
    </x-form.select>

    <x-form.select name="locale" :label="__('app.common.language')">
        <option value="">{{ __('app.common.system_default') }}</option>
        @foreach ($locales as $locale)
            <option value="{{ $locale }}" @selected(old('locale', $admin->locale) === $locale)>{{ __('app.locales.'.$locale) }}</option>
        @endforeach
    </x-form.select>

    <x-form.select name="employee_id" :label="__('app.admin_users.employee_id')" :help="__('app.admin_users.employee_id_help')">
        <option value="">{{ __('app.admin_users.no_employee') }}</option>
        @foreach ($employees as $employee)
            <option value="{{ $employee->id }}" @selected((string) old('employee_id', $admin->employee_id) === (string) $employee->id)>{{ $employee->label() }}</option>
        @endforeach
    </x-form.select>
</x-form.section>

<x-form.section :title="__('app.admin_users.roles')" :description="__('app.admin_users.roles_help')" data-section="roles">
    <fieldset class="md:col-span-2">
        <legend class="sr-only">{{ __('app.admin_users.roles') }}</legend>
        @php
            $checkedRoles = array_map('intval', (array) old('roles', $assignedRoleIds));
        @endphp
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($roles as $role)
                <label for="role-{{ $role->id }}" class="flex cursor-pointer items-start gap-3 rounded-lg border border-border-default p-3 transition-colors hover:bg-surface-subtle has-checked:border-action-primary-default has-checked:bg-action-primary-subtle">
                    <input id="role-{{ $role->id }}" type="checkbox" name="roles[]" value="{{ $role->id }}"
                        class="mt-1 size-4 shrink-0 accent-action-primary-default"
                        @checked(in_array($role->id, $checkedRoles, true))>
                    <span class="min-w-0">
                        <span class="block text-label-md text-text-primary">{{ $role->displayName() }}</span>
                        @if ($role->description)
                            <span class="block text-caption-md text-text-tertiary">{{ $role->description }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
        @foreach (['roles', 'roles.*'] as $key)
            @error($key) <p class="mt-2 text-caption-md text-feedback-danger-text" data-field-error>{{ $message }}</p> @enderror
        @endforeach
    </fieldset>
</x-form.section>

<x-form.section :title="__('app.admin_users.data_scopes')" :description="__('app.admin_users.data_scopes_help')" data-section="data-scopes">
    {{-- Branches, departments and employee groups are picked from the records the signed-in admin can see. --}}
    @foreach ($scopeTypes as $type)
        @php
            $selectedIds = array_map('intval', (array) old('scopes.'.$type->value, $scopes[$type->value] ?? []));
        @endphp
        @if (isset($scopeOptions[$type->value]))
            <fieldset class="md:col-span-2">
                <legend class="mb-2 text-label-md text-text-primary">{{ $type->label() }}</legend>
                <input type="hidden" name="scopes[{{ $type->value }}][]" value="">
                @if ($scopeOptions[$type->value]->isEmpty())
                    <p class="text-body-sm text-text-tertiary">{{ __('app.common.no_results') }}</p>
                @else
                    <div class="grid max-h-64 gap-2 overflow-y-auto rounded-lg border border-border-subtle p-3 sm:grid-cols-2">
                        @foreach ($scopeOptions[$type->value] as $option)
                            <x-form.checkbox :name="'scopes['.$type->value.'][]'" :id="'scope-'.$type->value.'-'.$option->id" :value="$option->id"
                                :label="$option->code.' · '.$option->name" :description="$option->branch?->name"
                                :checked="in_array($option->id, $selectedIds, true)" />
                        @endforeach
                    </div>
                @endif
                @foreach (['scopes.'.$type->value, 'scopes.'.$type->value.'.*'] as $key)
                    @error($key) <p class="mt-2 text-caption-md text-feedback-danger-text" data-field-error>{{ $message }}</p> @enderror
                @endforeach
            </fieldset>
        @else
            <x-form.input name="scopes[{{ $type->value }}]" id="scopes-{{ $type->value }}" :label="$type->label()" inputmode="numeric" dir="ltr"
                :error-keys="['scopes.'.$type->value.'.*']"
                :value="implode(', ', $selectedIds)" />
        @endif
    @endforeach
    @error('scopes') <p class="text-caption-md text-feedback-danger-text md:col-span-2" data-field-error>{{ $message }}</p> @enderror
</x-form.section>
