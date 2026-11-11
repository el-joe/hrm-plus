@extends('layouts.admin', ['subtitle' => __('attendance.corrections.subtitle')])

@section('title', __('attendance.corrections.title'))

@section('page-actions')
    @can('attendance.update')
        <x-button icon="plus" :href="route('admin.attendance.corrections.create')">{{ __('attendance.corrections.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.attendance.corrections.index')" :reset="route('admin.attendance.corrections.index', ['status' => ''])">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="__('attendance.common.search_employee')" :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="status" :label="__('attendance.fields.status')" hide-label class="md:w-48">
            <option value="">{{ __('attendance.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.checkbox name="mine" value="1" :label="__('attendance.corrections.awaiting_me')" :checked="request()->boolean('mine')" />
    </x-filter-bar>

    <x-table :caption="__('attendance.corrections.title')">
        <x-slot:head>
            <tr>
                <x-table.th>#</x-table.th>
                <x-table.th class="min-w-48">{{ __('attendance.fields.employee') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.date') }}</x-table.th>
                <x-table.th>{{ __('attendance.corrections.requested') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.reason') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.requested_by') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($corrections as $correction)
            <x-table.row data-href="{{ route('admin.attendance.corrections.show', $correction) }}">
                <x-table.td label="#" class="tabular-nums"><a href="{{ route('admin.attendance.corrections.show', $correction) }}" class="text-text-link hover:underline">{{ $correction->id }}</a></x-table.td>
                <x-table.td :label="__('attendance.fields.employee')">
                    <span class="block text-label-md">{{ $correction->employee->name }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $correction->employee->employee_number }}</span>
                </x-table.td>
                <x-table.td :label="__('attendance.fields.date')" class="tabular-nums"><span dir="ltr">{{ $correction->work_date->format('Y-m-d') }}</span></x-table.td>
                <x-table.td :label="__('attendance.corrections.requested')">@include('admin.attendance.corrections._requested')</x-table.td>
                <x-table.td :label="__('attendance.fields.reason')"><span class="line-clamp-2 max-w-xs">{{ $correction->reason }}</span></x-table.td>
                <x-table.td :label="__('attendance.fields.requested_by')">
                    {{ $correction->requester?->name ?? '—' }}
                    <span class="block text-caption-md text-text-tertiary">{{ $correction->source->label() }}</span>
                </x-table.td>
                <x-table.td :label="__('attendance.fields.status')">@include('admin.attendance.corrections._status')</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="inbox" :title="__('attendance.corrections.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$corrections" />
        </x-slot:footer>
    </x-table>
@endsection
