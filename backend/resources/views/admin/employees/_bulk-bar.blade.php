{{--
    Bulk operations on the rows ticked in the table below (their checkboxes belong to this form
    through form="bulk-form", so it works without JavaScript; resources/js/pages/employee-bulk.js
    shows the selection count and only the target field the chosen action needs).
    Variables: $bulk (branches, departments, groups, managers).
--}}
<form id="bulk-form" method="POST" action="{{ route('admin.employees.bulk') }}" data-bulk-form
    data-confirm="{{ __('employees.bulk.confirm') }}" data-confirm-title="{{ __('employees.bulk.title') }}" data-confirm-label="{{ __('employees.bulk.apply') }}"
    class="flex flex-col gap-3 rounded-lg border border-border-subtle bg-surface-default p-4 shadow-xs md:flex-row md:flex-wrap md:items-end" data-region="bulk">
    @csrf
    <p class="text-label-md text-text-secondary md:me-2 md:self-center" hidden data-js-only>
        <span data-bulk-count>0</span> {{ __('employees.bulk.selected') }}
    </p>
    <x-form.select name="action" id="bulk-action" :label="__('employees.bulk.action')" class="md:w-56" required data-bulk-action>
        <option value="">{{ __('employees.bulk.choose') }}</option>
        @foreach (App\Actions\Employees\BulkUpdateEmployees::ACTIONS as $action)
            <option value="{{ $action }}" @selected(old('action') === $action)>{{ __('employees.bulk.actions.'.$action) }}</option>
        @endforeach
    </x-form.select>
    <div data-bulk-target="assign_branch">
        <x-form.select name="branch_id" id="bulk-branch" :label="__('employees.fields.branch_id')" class="md:w-52">
            <option value="">—</option>
            @foreach ($bulk['branches'] as $branch)
                <option value="{{ $branch->id }}">{{ $branch->code }} · {{ $branch->name }}</option>
            @endforeach
        </x-form.select>
    </div>
    <div data-bulk-target="assign_department">
        <x-form.select name="department_id" id="bulk-department" :label="__('employees.fields.department_id')" class="md:w-56">
            <option value="">—</option>
            @foreach ($bulk['departments'] as $department)
                <option value="{{ $department->id }}">{{ $department->code }} · {{ $department->name }}</option>
            @endforeach
        </x-form.select>
    </div>
    <div data-bulk-target="assign_manager">
        <x-form.select name="manager_id" id="bulk-manager" :label="__('employees.fields.manager_id')" class="md:w-64">
            <option value="">—</option>
            @foreach ($bulk['managers'] as $manager)
                <option value="{{ $manager->id }}">{{ $manager->label() }}</option>
            @endforeach
        </x-form.select>
    </div>
    <div data-bulk-target="add_to_group remove_from_group">
        <x-form.select name="group_id" id="bulk-group" :label="__('employees.fields.groups')" class="md:w-52">
            <option value="">—</option>
            @foreach ($bulk['groups'] as $group)
                <option value="{{ $group->id }}">{{ $group->code }} · {{ $group->name }}</option>
            @endforeach
        </x-form.select>
    </div>
    <x-form.input name="reason" id="bulk-reason" :label="__('employees.fields.reason')" maxlength="1000" class="md:w-64" />
    <x-button variant="secondary" icon="check">{{ __('employees.bulk.apply') }}</x-button>
    @foreach (['ids', 'ids.*'] as $key)
        @error($key) <p class="w-full text-caption-md text-feedback-danger-text" data-field-error>{{ $message }}</p> @enderror
    @endforeach
</form>
