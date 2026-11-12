@extends('layouts.admin', ['subtitle' => __('performance.scales.subtitle')])

@section('title', __('performance.scales.title'))

@section('page-actions')
    @can('create', App\Models\RatingScale::class)
        <x-button :href="route('admin.rating-scales.create')" icon="plus">{{ __('performance.scales.create') }}</x-button>
    @endcan
@endsection

@section('content')
    @if ($errors->has('record'))
        <x-alert tone="danger">{{ $errors->first('record') }}</x-alert>
    @endif
    <x-filter-bar :action="route('admin.rating-scales.index')" :reset="route('admin.rating-scales.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="status" :label="__('performance.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('performance.fields.status') }}: {{ __('app.common.all') }}</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('structure.common.active') }}</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('structure.common.inactive') }}</option>
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('performance.scales.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('performance.fields.name') }}</x-table.th>
                <x-table.th class="min-w-72">{{ __('performance.scales.levels') }}</x-table.th>
                <x-table.th>{{ __('performance.cycles.title') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('performance.fields.name')">
                    <span class="text-label-md text-text-primary">{{ $record->name }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $record->code }}</span>
                </x-table.td>
                <x-table.td :label="__('performance.scales.levels')">
                    <ul class="flex flex-col gap-1">
                        @foreach ($record->levels->reverse() as $level)
                            <li class="flex flex-wrap items-center gap-2 text-body-sm">
                                <span class="w-6 tabular-nums" dir="ltr">{{ rtrim(rtrim((string) $level->value, '0'), '.') }}</span>
                                @include('admin.performance._rating', ['level' => $level])
                                <span class="text-caption-md text-text-tertiary">{{ __('performance.scales.from_score', ['score' => $level->min_score]) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-table.td>
                <x-table.td :label="__('performance.cycles.title')" class="tabular-nums">{{ $record->cycles_count }}</x-table.td>
                <x-table.td :label="__('performance.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'rating-scales'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="bar-chart" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
