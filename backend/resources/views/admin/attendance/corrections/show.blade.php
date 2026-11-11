@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('attendance.corrections.title'), 'url' => route('admin.attendance.corrections.index')]],
    'subtitle' => $correction->employee->employee_number.' · '.$correction->work_date->format('Y-m-d'),
])

@section('title', __('attendance.corrections.show', ['id' => $correction->id]))

@section('page-actions')
    @if ($day)
        <x-button variant="secondary" icon="eye" :href="route('admin.attendance.days.show', $day)">{{ __('attendance.corrections.open_day') }}</x-button>
    @endif
@endsection

@section('content')
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('attendance.corrections.details')" class="lg:col-span-2">
            <x-detail-list>
                <x-detail-item :label="__('attendance.fields.employee')">{{ $correction->employee->label() }}</x-detail-item>
                <x-detail-item :label="__('attendance.fields.date')"><span dir="ltr">{{ $correction->work_date->format('Y-m-d') }}</span></x-detail-item>
                <x-detail-item :label="__('attendance.fields.shift')">{{ $correction->shift ? $correction->shift->code.' · '.$correction->shift->timeRange() : '—' }}</x-detail-item>
                <x-detail-item :label="__('attendance.corrections.requested')">@include('admin.attendance.corrections._requested', ['timezone' => $timezone])</x-detail-item>
                <x-detail-item :label="__('attendance.fields.reason')">{{ $correction->reason }}</x-detail-item>
                <x-detail-item :label="__('attendance.fields.requested_by')">{{ $correction->requester?->name ?? '—' }} · {{ $correction->source->label() }}</x-detail-item>
                <x-detail-item :label="__('attendance.fields.status')">@include('admin.attendance.corrections._status')</x-detail-item>
            </x-detail-list>
        </x-card>

        <x-card :title="__('attendance.corrections.approval')">
            <ol class="flex flex-col gap-3">
                @foreach ($correction->approvalRequests->flatMap->actions as $action)
                    <li class="text-body-sm">
                        <span class="text-label-md">{{ __('attendance.approval_actions.'.$action->action->value) }}</span>
                        — {{ $action->actor?->name ?? '—' }}
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $action->created_at?->setTimezone($timezone)->format('Y-m-d H:i') }}</span>
                        @if ($action->reason)
                            <span class="block text-caption-md text-text-secondary">{{ $action->reason }}</span>
                        @endif
                    </li>
                @endforeach
                @if ($correction->approvalRequests->isEmpty())
                    <li class="text-body-sm text-text-tertiary">{{ __('attendance.corrections.no_approval_needed') }}</li>
                @endif
            </ol>

            @if ($canDecide)
                <div class="mt-4 flex flex-col gap-3 border-t border-border-subtle pt-4">
                    <form method="POST" action="{{ route('admin.attendance.corrections.decide', [$correction, 'approve']) }}">
                        @csrf
                        <x-button icon="check" class="w-full">{{ __('attendance.common.approve') }}</x-button>
                    </form>
                    <form method="POST" action="{{ route('admin.attendance.corrections.decide', [$correction, 'reject']) }}" class="flex flex-col gap-2" data-validate>
                        @csrf
                        <x-form.textarea name="reason" :label="__('attendance.common.reject_reason')" required rows="2" maxlength="1000" />
                        <x-button variant="danger" icon="x">{{ __('attendance.common.reject') }}</x-button>
                    </form>
                </div>
            @endif
        </x-card>
    </div>

    @if ($correction->rawEvents->isNotEmpty())
        <x-card :title="__('attendance.corrections.created_events')">
            <ul class="flex flex-col gap-1 text-body-sm">
                @foreach ($correction->rawEvents as $event)
                    <li>#{{ $event->id }} · {{ $event->kind->label() }} · {{ $event->direction?->label() ?? $event->attendanceStatus?->name }} · <span dir="ltr">{{ $event->kind->value === 'punch' ? $event->occurred_at->setTimezone($timezone)->format('Y-m-d H:i') : $event->work_date?->format('Y-m-d') }}</span></li>
                @endforeach
            </ul>
        </x-card>
    @endif
@endsection
