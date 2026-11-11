@extends('layouts.admin', ['subtitle' => __('employees.index.subtitle')])

@section('title', __('employees.index.title'))

@section('page-actions')
    @can('export', App\Models\Employee::class)
        <x-dropdown>
            <x-slot:trigger>
                <span class="inline-flex h-10 items-center gap-2 rounded-md border border-border-default bg-surface-default px-4 text-label-md text-text-primary shadow-xs hover:bg-surface-subtle">
                    <x-icon name="download" size="sm" /> {{ __('employees.index.export') }} <x-icon name="chevron-down" size="sm" />
                </span>
            </x-slot:trigger>
            <x-dropdown.item :href="route('admin.employees.export', [...array_filter($filters), 'format' => 'xlsx'])" icon="file-text">{{ __('employees.index.export_xlsx') }}</x-dropdown.item>
            <x-dropdown.item :href="route('admin.employees.export', [...array_filter($filters), 'format' => 'csv'])" icon="file-text">{{ __('employees.index.export_csv') }}</x-dropdown.item>
        </x-dropdown>
    @endcan
    @can('import', App\Models\Employee::class)
        <x-button variant="secondary" :href="route('admin.employees.import')" icon="upload">{{ __('employees.index.import') }}</x-button>
    @endcan
    @can('create', App\Models\Employee::class)
        <x-button :href="route('admin.employees.create')" icon="user-plus">{{ __('employees.index.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.employees.index')" :reset="route('admin.employees.index')" data-region="filters">
        <x-form.input name="search" id="search" type="search" icon="search" :label="__('app.common.search')" hide-label
            :placeholder="__('employees.index.search_placeholder')" :value="$filters['search'] ?? ''" class="md:w-72" data-filter-debounce />
        <x-form.select name="status" id="filter-status" :label="__('employees.fields.status')" hide-label class="md:w-40">
            <option value="">{{ __('employees.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($options['statuses'] as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        @include('admin.structure._id-filter', ['name' => 'branch_id', 'label' => __('employees.fields.branch_id'), 'options' => $options['branches']])
        @include('admin.structure._id-filter', ['name' => 'department_id', 'label' => __('employees.fields.department_id'), 'options' => $options['departments']])
        @include('admin.structure._id-filter', ['name' => 'job_title_id', 'label' => __('employees.fields.job_title_id'), 'options' => $options['jobTitles']])
        @include('admin.structure._id-filter', ['name' => 'employment_type_id', 'label' => __('employees.fields.employment_type_id'), 'options' => $options['employmentTypes']])
        @if ($options['groups']->isNotEmpty())
            @include('admin.structure._id-filter', ['name' => 'group_id', 'label' => __('employees.fields.groups'), 'options' => $options['groups']])
        @endif
    </x-filter-bar>

    @if (session('bulkSkipped'))
        <x-alert tone="warning" :title="__('employees.bulk.skipped_title')" dismissible data-region="bulk-skipped">
            <ul class="mt-1 list-disc ps-5">
                @foreach (session('bulkSkipped') as $number => $reason)
                    <li><span dir="ltr">{{ $number }}</span>: {{ $reason }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    @if ($bulk)
        @include('admin.employees._bulk-bar')
    @endif

    <x-table data-region="table" :caption="__('employees.index.title')">
        <x-slot:head>
            <tr>
                @if ($bulk)
                    <x-table.th class="w-10">
                        <input type="checkbox" class="size-4 accent-action-primary-default" data-table-select-all aria-label="{{ __('employees.bulk.select_all') }}">
                    </x-table.th>
                @endif
                <x-table.th sort="name" class="min-w-64">{{ __('employees.fields.name') }}</x-table.th>
                <x-table.th sort="department">{{ __('employees.fields.department_id') }}</x-table.th>
                <x-table.th>{{ __('employees.fields.job_title_id') }}</x-table.th>
                <x-table.th>{{ __('employees.fields.manager_id') }}</x-table.th>
                <x-table.th sort="hire_date">{{ __('employees.fields.hire_date') }}</x-table.th>
                <x-table.th sort="status">{{ __('employees.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($employees as $employee)
            <x-table.row :data-href="route('admin.employees.show', $employee)">
                @if ($bulk)
                    <x-table.td>
                        <input type="checkbox" form="bulk-form" name="ids[]" value="{{ $employee->id }}" class="size-4 accent-action-primary-default"
                            data-table-select aria-label="{{ __('employees.bulk.select', ['name' => $employee->name]) }}">
                    </x-table.td>
                @endif
                <x-table.td :label="__('employees.fields.name')">
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$employee->name" size="sm" :src="$employee->photo_path ? route('admin.employees.photo', $employee) : null" />
                        <div class="min-w-0">
                            <a href="{{ route('admin.employees.show', $employee) }}" class="block truncate text-label-md text-text-primary hover:text-text-link">{{ $employee->name }}</a>
                            <p class="truncate text-caption-md text-text-tertiary"><span dir="ltr">{{ $employee->employee_number }}</span>@if ($employee->email) · <span dir="ltr">{{ $employee->email }}</span>@endif</p>
                        </div>
                    </div>
                </x-table.td>
                <x-table.td :label="__('employees.fields.department_id')">
                    <span class="block text-body-md text-text-primary">{{ $employee->department?->name }}</span>
                    <span class="block text-caption-md text-text-tertiary">{{ $employee->branch?->name }}</span>
                </x-table.td>
                <x-table.td :label="__('employees.fields.job_title_id')" class="text-text-secondary">
                    {{ $employee->jobTitle?->name ?? '—' }}
                    <span class="block text-caption-md text-text-tertiary">{{ $employee->employmentType?->name }}</span>
                </x-table.td>
                <x-table.td :label="__('employees.fields.manager_id')" class="text-text-secondary">{{ $employee->manager?->name ?? '—' }}</x-table.td>
                <x-table.td :label="__('employees.fields.hire_date')" class="whitespace-nowrap tabular-nums text-text-secondary"><span dir="ltr">{{ $employee->hire_date?->toDateString() }}</span></x-table.td>
                <x-table.td :label="__('employees.fields.status')">@include('admin.employees._status', ['status' => $employee->status])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="$bulk ? 7 : 6" icon="users" :title="__('employees.index.empty')" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$employees" />
        </x-slot:footer>
    </x-table>
@endsection
