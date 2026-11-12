@extends('layouts.admin', ['subtitle' => __('training.skills.subtitle')])

@section('title', __('training.skills.title'))

@section('page-actions')
    @can('viewAny', App\Models\EmployeeSkill::class)
        <x-button variant="secondary" :href="route('admin.skills-matrix')" icon="layout-grid">{{ __('training.skills_matrix.title') }}</x-button>
    @endcan
    @can('create', App\Models\Skill::class)
        <x-button :href="route('admin.skills.create')" icon="plus">{{ __('training.skills.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.skills.index')" :reset="route('admin.skills.index')">
        @include('admin.structure._filters')
    </x-filter-bar>

    <x-table :caption="__('training.skills.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('training.fields.name') }}</x-table.th>
                <x-table.th>{{ __('training.fields.category') }}</x-table.th>
                <x-table.th>{{ __('training.skills.holders') }}</x-table.th>
                <x-table.th>{{ __('training.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('training.fields.name')">@include('admin.structure._name-cell')</x-table.td>
                <x-table.td :label="__('training.fields.category')">{{ $record->category ?? '—' }}</x-table.td>
                <x-table.td :label="__('training.skills.holders')" class="tabular-nums">
                    <a class="text-text-link hover:underline" href="{{ route('admin.skills-matrix', ['skill_id' => $record->id]) }}">{{ $record->employee_skills_count }}</a>
                </x-table.td>
                <x-table.td :label="__('training.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'skills'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="shield-check" :title="__('training.skills.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
