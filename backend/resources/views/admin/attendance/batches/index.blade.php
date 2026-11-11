@extends('layouts.admin', ['subtitle' => __('attendance.batches.subtitle')])

@section('title', __('attendance.batches.title'))

@section('page-actions')
    @can('attendance.create')
        <x-button variant="secondary" icon="upload" :href="route('admin.attendance.import')">{{ __('attendance.import.title') }}</x-button>
        <x-button icon="check-circle" :href="route('admin.attendance.bulk.create')">{{ __('attendance.bulk.title') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.attendance.batches.index')" :reset="route('admin.attendance.batches.index')">
        <x-form.select name="status" :label="__('attendance.fields.status')" hide-label class="md:w-48">
            <option value="">{{ __('attendance.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('attendance.batches.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('attendance.fields.reference') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.source') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.date') }}</x-table.th>
                <x-table.th>{{ __('attendance.batches.entries') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.requested_by') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($batches as $batch)
            <x-table.row data-href="{{ route('admin.attendance.batches.show', $batch) }}">
                <x-table.td :label="__('attendance.fields.reference')"><a href="{{ route('admin.attendance.batches.show', $batch) }}" class="text-label-md text-text-link hover:underline" dir="ltr">{{ $batch->reference() }}</a></x-table.td>
                <x-table.td :label="__('attendance.fields.source')">{{ $batch->source->label() }} @if ($batch->file_name)<span class="block text-caption-md text-text-tertiary">{{ $batch->file_name }}</span>@endif</x-table.td>
                <x-table.td :label="__('attendance.fields.date')" class="tabular-nums"><span dir="ltr">{{ $batch->work_date?->format('Y-m-d') ?? '—' }}</span></x-table.td>
                <x-table.td :label="__('attendance.batches.entries')" class="tabular-nums">{{ $batch->entries_count }}</x-table.td>
                <x-table.td :label="__('attendance.fields.requested_by')">{{ $batch->creator?->name }} <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $batch->created_at->format('Y-m-d H:i') }}</span></x-table.td>
                <x-table.td :label="__('attendance.fields.status')">@include('admin.attendance.batches._status')</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="upload" :title="__('attendance.batches.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$batches" />
        </x-slot:footer>
    </x-table>
@endsection
