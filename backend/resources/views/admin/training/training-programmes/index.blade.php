@extends('layouts.admin', ['subtitle' => __('training.programmes.subtitle')])

@section('title', __('training.programmes.title'))

@section('page-actions')
    @can('create', App\Models\TrainingProgramme::class)
        <x-button :href="route('admin.training-programmes.create')" icon="plus">{{ __('training.programmes.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.training-programmes.index')" :reset="route('admin.training-programmes.index')">
        @include('admin.structure._filters')
    </x-filter-bar>

    <x-table :caption="__('training.programmes.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('training.fields.name') }}</x-table.th>
                <x-table.th>{{ __('training.fields.description') }}</x-table.th>
                <x-table.th>{{ __('training.courses.title') }}</x-table.th>
                <x-table.th>{{ __('training.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('training.fields.name')">@include('admin.structure._name-cell')</x-table.td>
                <x-table.td :label="__('training.fields.description')" class="max-w-md text-body-sm text-text-secondary">{{ Illuminate\Support\Str::limit($record->description, 140) ?: '—' }}</x-table.td>
                <x-table.td :label="__('training.courses.title')" class="tabular-nums">
                    <a class="text-text-link hover:underline" href="{{ route('admin.training-courses.index', ['training_programme_id' => $record->id]) }}">{{ $record->courses_count }}</a>
                </x-table.td>
                <x-table.td :label="__('training.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'training-programmes'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="layers" :title="__('training.programmes.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
