@extends('layouts.admin', ['subtitle' => __('structure.job_grades.subtitle')])

@section('title', __('structure.job_grades.title'))

@section('page-actions')
    @can('create', App\Models\JobGrade::class)
        <x-button :href="route('admin.job-grades.create')" icon="plus">{{ __('structure.job_grades.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.job-grades.index')" :reset="route('admin.job-grades.index')" data-region="filters">
        @include('admin.structure._filters')
    </x-filter-bar>

    <x-table data-region="table" :caption="__('structure.job_grades.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="level">{{ __('structure.fields.level') }}</x-table.th>
                <x-table.th sort="code">{{ __('structure.fields.code') }}</x-table.th>
                <x-table.th sort="name" class="min-w-48">{{ __('structure.fields.name') }}</x-table.th>
                @can('employee_financials.view')
                    <x-table.th>{{ __('structure.job_grades.salary_band') }} <span class="text-text-tertiary" dir="ltr">({{ $currency }})</span></x-table.th>
                @endcan
                <x-table.th sort="positions">{{ __('structure.departments.positions') }}</x-table.th>
                <x-table.th>{{ __('structure.common.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('structure.fields.level')" class="tabular-nums">{{ $record->level }}</x-table.td>
                <x-table.td :label="__('structure.fields.code')"><span dir="ltr" class="text-label-md">{{ $record->code }}</span></x-table.td>
                <x-table.td :label="__('structure.fields.name')" class="text-label-md text-text-primary">{{ $record->name }}</x-table.td>
                @can('employee_financials.view')
                    <x-table.td :label="__('structure.job_grades.salary_band')" class="whitespace-nowrap tabular-nums text-text-secondary">
                        @if ($record->min_salary !== null || $record->max_salary !== null)
                            <span dir="ltr">{{ $record->min_salary !== null ? number_format((float) $record->min_salary, 2) : '…' }} – {{ $record->max_salary !== null ? number_format((float) $record->max_salary, 2) : '…' }}</span>
                        @else
                            {{ __('structure.job_grades.no_band') }}
                        @endif
                    </x-table.td>
                @endcan
                <x-table.td :label="__('structure.departments.positions')" class="tabular-nums">{{ $record->positions_count }}</x-table.td>
                <x-table.td :label="__('structure.common.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'job-grades'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="award" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
