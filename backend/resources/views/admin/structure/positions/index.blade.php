@extends('layouts.admin', ['subtitle' => __('structure.positions.subtitle')])

@section('title', __('structure.positions.title'))

@section('page-actions')
    @can('departments.view')
        <x-button variant="secondary" :href="route('admin.org-chart', ['view' => 'positions'])" icon="sitemap">{{ __('structure.org_chart.view_positions') }}</x-button>
    @endcan
    @can('create', App\Models\Position::class)
        <x-button :href="route('admin.positions.create')" icon="plus">{{ __('structure.positions.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.positions.index')" :reset="route('admin.positions.index')" data-region="filters">
        @include('admin.structure._filters')
        @include('admin.structure._id-filter', ['name' => 'department_id', 'label' => __('structure.fields.department_id'), 'options' => $departments])
        @include('admin.structure._id-filter', ['name' => 'job_title_id', 'label' => __('structure.fields.job_title_id'), 'options' => $jobTitles])
        @include('admin.structure._id-filter', ['name' => 'job_grade_id', 'label' => __('structure.fields.job_grade_id'), 'options' => $jobGrades])
    </x-filter-bar>

    <x-table data-region="table" :caption="__('structure.positions.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="name" class="min-w-56">{{ __('structure.fields.name') }}</x-table.th>
                <x-table.th sort="department">{{ __('structure.fields.department_id') }}</x-table.th>
                <x-table.th>{{ __('structure.fields.job_grade_id') }}</x-table.th>
                <x-table.th>{{ __('structure.positions.reports_to') }}</x-table.th>
                <x-table.th sort="headcount">{{ __('structure.fields.headcount') }}</x-table.th>
                <x-table.th>{{ __('structure.common.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('structure.fields.name')">
                    @include('admin.structure._name-cell', ['sub' => $record->jobTitle?->name])
                </x-table.td>
                <x-table.td :label="__('structure.fields.department_id')">{{ $record->department?->name }}</x-table.td>
                <x-table.td :label="__('structure.fields.job_grade_id')" class="text-text-secondary">{{ $record->jobGrade?->name ?? '—' }}</x-table.td>
                <x-table.td :label="__('structure.positions.reports_to')" class="text-text-secondary">{{ $record->reportsTo?->name ?? __('structure.positions.top_of_hierarchy') }}</x-table.td>
                <x-table.td :label="__('structure.fields.headcount')" class="tabular-nums">{{ $record->headcount }}</x-table.td>
                <x-table.td :label="__('structure.common.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'positions'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="briefcase" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
