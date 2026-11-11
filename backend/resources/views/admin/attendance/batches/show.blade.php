@php
    $summary = $batch->summary ?? [];
    $tzFor = fn ($entry) => app(\App\Services\Attendance\AttendanceClock::class)->timezoneForBranch($entry->employee?->branch_id);
@endphp
@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('attendance.batches.title'), 'url' => route('admin.attendance.batches.index')]],
    'subtitle' => $batch->source->label().($batch->work_date ? ' · '.$batch->work_date->translatedFormat('l j F Y') : '').($batch->file_name ? ' · '.$batch->file_name : ''),
])

@section('title', $batch->isDraft() ? __('attendance.batches.confirm_title') : __('attendance.batches.show', ['reference' => $batch->reference()]))

@section('content')
    @if ($batch->isDraft())
        <x-alert tone="warning" :title="__('attendance.batches.not_saved_yet')">{{ __('attendance.batches.not_saved_help') }}</x-alert>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        @include('admin.attendance.batches._status')
        <span class="text-label-md text-text-secondary" dir="ltr">{{ $batch->reference() }}</span>
        @foreach ($filterLabels as $key => $label)
            <x-badge tone="neutral">{{ __('attendance.batches.filters.'.$key) }}: {{ $label }}</x-badge>
        @endforeach
    </div>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6" data-region="batch-summary">
        <x-stat-card :label="__('attendance.batches.employees')" :value="$entries->count()" icon="users" />
        <x-stat-card :label="__('attendance.enums.entry_actions.present')" :value="$summary['present'] ?? 0" icon="check-circle" />
        <x-stat-card :label="__('attendance.enums.entry_actions.late')" :value="$summary['late'] ?? 0" icon="clock" />
        <x-stat-card :label="__('attendance.enums.entry_actions.custom_times')" :value="$summary['custom_times'] ?? 0" icon="pencil" />
        @foreach ($summary['statuses'] ?? [] as $code => $count)
            <x-stat-card :label="$statusNames[$code] ?? $code" :value="$count" icon="calendar" />
        @endforeach
        @if (($summary['skipped_existing'] ?? 0) > 0)
            <x-stat-card :label="__('attendance.batches.skipped_existing')" :value="$summary['skipped_existing']" icon="info" :hint="__('attendance.batches.skipped_existing_help')" />
        @endif
    </div>

    @if ($batch->isDraft() && $canConfirm)
        <x-card data-region="confirm">
            <p class="text-body-md text-text-primary">
                {{ trans_choice('attendance.batches.confirm_question', $entries->count(), ['count' => $entries->count()]) }}
            </p>
            @if ($requiresApproval)
                <p class="mt-2 text-body-sm text-text-secondary">{{ __('attendance.batches.needs_approval') }}</p>
            @endif
            @if ($batch->note)
                <p class="mt-2 text-body-sm text-text-tertiary">{{ __('attendance.bulk.batch_note') }}: {{ $batch->note }}</p>
            @endif
            @error('batch')
                <x-alert tone="danger" class="mt-3">{{ $message }}</x-alert>
            @enderror
            <div class="mt-4 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.attendance.batches.confirm', $batch) }}">
                    @csrf
                    <x-button icon="check">{{ $requiresApproval ? __('attendance.batches.submit_for_approval') : __('attendance.batches.confirm_apply') }}</x-button>
                </form>
                <form method="POST" action="{{ route('admin.attendance.batches.discard', $batch) }}">
                    @csrf
                    <x-button variant="secondary" icon="arrow-left">{{ __('attendance.batches.back_edit') }}</x-button>
                </form>
            </div>
        </x-card>
    @endif

    @if ($canDecide)
        <x-card :title="__('attendance.batches.decision')" data-region="decide">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <form method="POST" action="{{ route('admin.attendance.batches.decide', [$batch, 'approve']) }}">
                    @csrf
                    <x-button icon="check">{{ __('attendance.common.approve') }}</x-button>
                </form>
                <form method="POST" action="{{ route('admin.attendance.batches.decide', [$batch, 'reject']) }}" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-end" data-validate>
                    @csrf
                    <x-form.input name="reason" :label="__('attendance.common.reject_reason')" required maxlength="1000" class="sm:w-80" />
                    <x-button variant="danger" icon="x">{{ __('attendance.common.reject') }}</x-button>
                </form>
            </div>
        </x-card>
    @endif

    <x-table :caption="__('attendance.batches.entries')" data-region="entries">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('attendance.fields.employee') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.date') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.shift') }}</x-table.th>
                <x-table.th>{{ __('attendance.bulk.mark_as') }}</x-table.th>
                <x-table.th>{{ __('attendance.bulk.times') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.note') }}</x-table.th>
            </tr>
        </x-slot:head>
        @foreach ($entries->sortBy(fn ($entry) => $entry->action->value === 'present' ? 1 : 0) as $entry)
            @php $tz = $tzFor($entry); @endphp
            <x-table.row>
                <x-table.td :label="__('attendance.fields.employee')">
                    <span class="block text-label-md">{{ $entry->employee?->name }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $entry->employee?->employee_number }}</span>
                </x-table.td>
                <x-table.td :label="__('attendance.fields.date')" class="tabular-nums"><span dir="ltr">{{ $entry->work_date->format('Y-m-d') }}</span></x-table.td>
                <x-table.td :label="__('attendance.fields.shift')">{{ $entry->shift?->code ?? '—' }}</x-table.td>
                <x-table.td :label="__('attendance.bulk.mark_as')">
                    <x-badge :tone="$entry->action->value === 'present' ? 'success' : 'warning'">{{ $entry->attendanceStatus?->name ?? $entry->action->label() }}</x-badge>
                </x-table.td>
                <x-table.td :label="__('attendance.bulk.times')" class="tabular-nums">
                    <span dir="ltr">{{ $entry->check_in_at?->setTimezone($tz)->format('H:i') ?? '' }}@if ($entry->check_out_at) – {{ $entry->check_out_at->setTimezone($tz)->format('H:i') }}@endif</span>
                </x-table.td>
                <x-table.td :label="__('attendance.fields.note')">{{ $entry->note }}</x-table.td>
            </x-table.row>
        @endforeach
    </x-table>
@endsection
