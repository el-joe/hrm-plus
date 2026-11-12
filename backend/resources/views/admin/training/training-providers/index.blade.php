@extends('layouts.admin', ['subtitle' => __('training.providers.subtitle')])

@section('title', __('training.providers.title'))

@section('page-actions')
    @can('create', App\Models\TrainingProvider::class)
        <x-button :href="route('admin.training-providers.create')" icon="plus">{{ __('training.providers.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.training-providers.index')" :reset="route('admin.training-providers.index')">
        @include('admin.structure._filters')
    </x-filter-bar>

    <x-table :caption="__('training.providers.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('training.fields.name') }}</x-table.th>
                <x-table.th>{{ __('training.fields.type') }}</x-table.th>
                <x-table.th>{{ __('training.fields.contact') }}</x-table.th>
                <x-table.th>{{ __('training.courses.title') }}</x-table.th>
                <x-table.th>{{ __('training.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('training.fields.name')">@include('admin.structure._name-cell')</x-table.td>
                <x-table.td :label="__('training.fields.type')">{{ $record->type->label() }}</x-table.td>
                <x-table.td :label="__('training.fields.contact')" class="text-caption-md text-text-secondary">
                    @if ($record->contact_name)<span class="block">{{ $record->contact_name }}</span>@endif
                    @if ($record->email)<span class="block" dir="ltr">{{ $record->email }}</span>@endif
                    @if ($record->phone)<span class="block" dir="ltr">{{ $record->phone }}</span>@endif
                </x-table.td>
                <x-table.td :label="__('training.courses.title')" class="tabular-nums">
                    <a class="text-text-link hover:underline" href="{{ route('admin.training-courses.index', ['training_provider_id' => $record->id]) }}">{{ $record->courses_count }}</a>
                </x-table.td>
                <x-table.td :label="__('training.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'training-providers'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="building" :title="__('training.providers.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
