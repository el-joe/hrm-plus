@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('structure.departments.title'), 'url' => route('admin.departments.index')]],
    'subtitle' => $department->code,
])

@section('title', $department->name)

@section('page-actions')
    @can('update', $department)
        <x-button variant="secondary" :href="route('admin.departments.edit', $department)" icon="pencil">{{ __('app.actions.edit') }}</x-button>
    @endcan
    @can('create', App\Models\Position::class)
        <x-button :href="route('admin.positions.create')" icon="plus">{{ __('structure.positions.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('structure.common.details')" class="lg:col-span-2">
            <x-detail-list>
                <x-detail-item :label="__('structure.fields.name_ar')"><span lang="ar" dir="rtl">{{ $department->name_ar }}</span></x-detail-item>
                <x-detail-item :label="__('structure.fields.name_en')"><span lang="en" dir="ltr">{{ $department->name_en }}</span></x-detail-item>
                <x-detail-item :label="__('structure.fields.parent_id')">
                    @if ($department->parent)
                        <a href="{{ route('admin.departments.show', $department->parent) }}" class="text-text-link hover:underline">{{ $department->parent->name }}</a>
                    @else
                        {{ __('structure.departments.top_level') }}
                    @endif
                </x-detail-item>
                <x-detail-item :label="__('structure.fields.branch_id')">{{ $department->branch?->name ?? __('structure.departments.company_wide') }}</x-detail-item>
                <x-detail-item :label="__('structure.fields.cost_center_id')">{{ $department->costCenter ? $department->costCenter->code.' · '.$department->costCenter->name : __('structure.common.not_set') }}</x-detail-item>
                <x-detail-item :label="__('structure.departments.head')">{{ $department->headPosition?->name ?? __('structure.common.not_set') }}</x-detail-item>
                <x-detail-item :label="__('structure.common.status')">@include('admin.structure._status-badge', ['active' => $department->is_active])</x-detail-item>
                @if ($department->description)
                    <x-detail-item :label="__('structure.fields.description')" class="sm:col-span-2">{{ $department->description }}</x-detail-item>
                @endif
            </x-detail-list>
        </x-card>

        <div class="flex flex-col gap-4">
            <x-stat-card :label="__('structure.departments.positions')" :value="$positions->count()" icon="briefcase" />
            <x-stat-card :label="__('structure.departments.headcount')" :value="$positions->sum('headcount')" icon="users" />
            <x-stat-card :label="__('structure.departments.teams')" :value="$teams->count()" icon="users" />
        </div>
    </div>

    <x-card :title="__('structure.departments.positions')" padding="none">
        <x-table :caption="__('structure.departments.positions')">
            <x-slot:head>
                <tr>
                    <x-table.th class="min-w-56">{{ __('structure.fields.name') }}</x-table.th>
                    <x-table.th>{{ __('structure.fields.job_grade_id') }}</x-table.th>
                    <x-table.th>{{ __('structure.positions.reports_to') }}</x-table.th>
                    <x-table.th>{{ __('structure.fields.headcount') }}</x-table.th>
                    <x-table.th>{{ __('structure.common.status') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($positions as $position)
                <x-table.row>
                    <x-table.td :label="__('structure.fields.name')">
                        @include('admin.structure._name-cell', [
                            'record' => $position,
                            'url' => auth()->user()->can('update', $position) ? route('admin.positions.edit', $position) : null,
                            'sub' => $position->jobTitle?->name,
                        ])
                    </x-table.td>
                    <x-table.td :label="__('structure.fields.job_grade_id')" class="text-text-secondary">{{ $position->jobGrade?->name ?? '—' }}</x-table.td>
                    <x-table.td :label="__('structure.positions.reports_to')" class="text-text-secondary">{{ $position->reportsTo?->name ?? __('structure.positions.top_of_hierarchy') }}</x-table.td>
                    <x-table.td :label="__('structure.fields.headcount')" class="tabular-nums">{{ $position->headcount }}</x-table.td>
                    <x-table.td :label="__('structure.common.status')">@include('admin.structure._status-badge', ['active' => $position->is_active])</x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="5" icon="briefcase" :title="__('structure.departments.no_positions')" />
            @endforelse
        </x-table>
    </x-card>

    <div class="grid gap-4 lg:grid-cols-2 lg:gap-6">
        <x-card :title="__('structure.departments.sub_departments')">
            @forelse ($children as $child)
                <a href="{{ route('admin.departments.show', $child) }}" class="flex items-center justify-between gap-3 border-b border-border-subtle py-3 last:border-0 hover:text-text-link">
                    <span class="min-w-0">
                        <span class="block truncate text-label-md">{{ $child->name }}</span>
                        <span class="block text-caption-md text-text-tertiary"><span dir="ltr">{{ $child->code }}</span> · {{ $child->branch?->name ?? __('structure.departments.company_wide') }}</span>
                    </span>
                    <x-badge>{{ __('structure.departments.positions') }}: {{ $child->positions_count }}</x-badge>
                </a>
            @empty
                <p class="text-body-sm text-text-tertiary">{{ __('structure.departments.no_children') }}</p>
            @endforelse
        </x-card>

        <x-card :title="__('structure.departments.teams')">
            @forelse ($teams as $team)
                <div class="flex items-center justify-between gap-3 border-b border-border-subtle py-3 last:border-0">
                    <span class="min-w-0">
                        <span class="block truncate text-label-md text-text-primary">{{ $team->name }}</span>
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $team->code }}</span>
                    </span>
                    @include('admin.structure._status-badge', ['active' => $team->is_active])
                </div>
            @empty
                <p class="text-body-sm text-text-tertiary">{{ __('structure.departments.no_teams') }}</p>
            @endforelse
        </x-card>
    </div>
@endsection
