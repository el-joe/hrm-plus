@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('payroll.entries.'.$resource.'.title'), 'url' => route('admin.'.$resource.'.index')]],
    'subtitle' => __('payroll.help.bulk'),
])

@section('title', __('payroll.entries.'.$resource.'.bulk'))

@section('content')
    <x-filter-bar :action="route('admin.'.$resource.'.bulk')" :reset="route('admin.'.$resource.'.bulk')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="branch_id" :label="__('payroll.fields.branch_id')" hide-label class="md:w-48">
            <option value="">{{ __('payroll.fields.branch_id') }}: {{ __('app.common.all') }}</option>
            @foreach ($targetOptions['branches'] as $branch)
                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="department_id" :label="__('payroll.fields.department_id')" hide-label class="md:w-56">
            <option value="">{{ __('payroll.fields.department_id') }}: {{ __('app.common.all') }}</option>
            @foreach ($targetOptions['departments'] as $department)
                <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->code }} · {{ $department->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="employee_group_id" :label="__('payroll.fields.employee_group_id')" hide-label class="md:w-48">
            <option value="">{{ __('payroll.fields.employee_group_id') }}: {{ __('app.common.all') }}</option>
            @foreach ($targetOptions['groups'] as $group)
                <option value="{{ $group->id }}" @selected((string) ($filters['employee_group_id'] ?? '') === (string) $group->id)>{{ $group->code }} · {{ $group->name }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <form method="POST" action="{{ route('admin.'.$resource.'.bulk.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        <x-card :title="__('payroll.entries.select_employees')" padding="none" data-region="bulk-employees">
            <x-slot:actions>
                <span class="text-caption-md text-text-secondary" data-check-count="bulk" data-template="{{ __('payroll.entries.selected', ['count' => ':count']) }}">{{ __('payroll.entries.selected', ['count' => count(old('employee_ids', []))]) }}</span>
            </x-slot:actions>
            @error('employee_ids')
                <div class="px-5 pt-4"><x-alert tone="danger">{{ $message }}</x-alert></div>
            @enderror
            @if ($employees->isEmpty())
                <div class="px-5 py-6"><x-empty-state icon="users" :title="__('payroll.entries.no_employees')" /></div>
            @else
                <div class="max-h-[28rem] overflow-y-auto">
                <x-table :caption="__('payroll.entries.select_employees')">
                    <x-slot:head>
                        <tr>
                            <x-table.th class="w-10">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" class="size-4 rounded-xs border-border-default accent-primary-600" data-check-all="bulk" hidden data-js-only aria-label="{{ __('payroll.entries.select_all') }}">
                                </label>
                            </x-table.th>
                            <x-table.th>{{ __('payroll.fields.employee_id') }}</x-table.th>
                            <x-table.th>{{ __('payroll.fields.department_id') }}</x-table.th>
                            <x-table.th>{{ __('payroll.fields.branch_id') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($employees as $employee)
                        <x-table.row>
                            <x-table.td>
                                <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" id="bulk-{{ $employee->id }}" class="size-4 rounded-xs border-border-default accent-primary-600"
                                    data-check-group="bulk" data-check-counted="bulk" @checked(in_array((string) $employee->id, array_map('strval', old('employee_ids', [])), true))>
                            </x-table.td>
                            <x-table.td :label="__('payroll.fields.employee_id')"><label for="bulk-{{ $employee->id }}" class="cursor-pointer">{{ $employee->label() }}</label></x-table.td>
                            <x-table.td :label="__('payroll.fields.department_id')">{{ $employee->department?->name }}</x-table.td>
                            <x-table.td :label="__('payroll.fields.branch_id')">{{ $employee->branch?->name }}</x-table.td>
                        </x-table.row>
                    @endforeach
                </x-table>
                </div>
            @endif
        </x-card>

        <x-form.section :title="__('payroll.entries.what')">
            @include('admin.payroll.entries._fields')
            <div class="md:col-span-2">
                <x-form.input name="note" maxlength="1000" :label="__('payroll.fields.note')" :value="old('note')" />
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.'.$resource.'.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button icon="check">{{ __('payroll.entries.'.$resource.'.bulk') }}</x-button>
        </x-form.actions>
    </form>
@endsection
