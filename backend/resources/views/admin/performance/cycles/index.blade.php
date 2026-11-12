@extends('layouts.admin', ['subtitle' => __('performance.cycles.subtitle')])

@section('title', __('performance.cycles.title'))

@section('page-actions')
    @can('create', App\Models\PerformanceCycle::class)
        <x-button icon="plus" :href="route('admin.performance-cycles.create')">{{ __('performance.cycles.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.performance-cycles.index')" :reset="route('admin.performance-cycles.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="type" :label="__('performance.fields.type')" hide-label class="md:w-48">
            <option value="">{{ __('performance.fields.type') }}: {{ __('app.common.all') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" :label="__('performance.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('performance.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('performance.cycles.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-52">{{ __('performance.fields.name') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.type') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.period') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.rating_scale') }}</x-table.th>
                <x-table.th class="min-w-44">{{ __('performance.cycles.completion') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($cycles as $cycle)
            <x-table.row :data-href="route('admin.performance-cycles.show', $cycle)">
                <x-table.td :label="__('performance.fields.name')">
                    <a href="{{ route('admin.performance-cycles.show', $cycle) }}" class="text-label-md text-text-link hover:underline">{{ $cycle->name }}</a>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $cycle->code }}</span>
                </x-table.td>
                <x-table.td :label="__('performance.fields.type')">{{ $cycle->type->label() }}</x-table.td>
                <x-table.td :label="__('performance.fields.period')" class="whitespace-nowrap"><span dir="ltr">{{ $cycle->period_start->toDateString() }} → {{ $cycle->period_end->toDateString() }}</span></x-table.td>
                <x-table.td :label="__('performance.fields.rating_scale')">{{ $cycle->ratingScale?->name }}</x-table.td>
                <x-table.td :label="__('performance.cycles.completion')">
                    @if ($cycle->reviews_count > 0)
                        @include('admin.performance._progress', ['value' => bcdiv(bcmul((string) $cycle->finalised_count, '100', 4), (string) $cycle->reviews_count, 2), 'label' => __('performance.cycles.completion')])
                        <span class="text-caption-md text-text-tertiary">{{ __('performance.cycles.finalised_of', ['done' => $cycle->finalised_count, 'total' => $cycle->reviews_count]) }}</span>
                    @else
                        <span class="text-text-tertiary">—</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('performance.fields.status')"><x-badge :tone="$cycle->status->tone()" dot>{{ $cycle->status->label() }}</x-badge></x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="refresh" :title="__('performance.cycles.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$cycles" />
        </x-slot:footer>
    </x-table>
@endsection
