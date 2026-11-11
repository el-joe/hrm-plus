@php
    use App\Services\Attendance\AttendanceClock;
    use App\Support\Duration;
    $tz = $day->timezone;
    $calc = $day->calculation ?? [];
    $rules = $calc['policy']['rules'] ?? [];
@endphp
@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('attendance.daily.title'), 'url' => route('admin.attendance.daily', ['date' => $day->work_date->format('Y-m-d')])]],
    'subtitle' => $day->employee->employee_number.' · '.$day->work_date->translatedFormat('l j F Y'),
])

@section('title', $day->employee->name)

@section('page-actions')
    @can('correct', $day)
        <x-button variant="secondary" icon="pencil" :href="route('admin.attendance.corrections.create', ['employee_id' => $day->employee_id, 'date' => $day->work_date->format('Y-m-d'), 'shift_id' => $day->shift_id])">{{ __('attendance.days.correct') }}</x-button>
    @endcan
    @can('reprocess', $day)
        <form method="POST" action="{{ route('admin.attendance.days.reprocess', $day) }}">
            @csrf
            <x-button variant="secondary" icon="history">{{ __('attendance.days.reprocess') }}</x-button>
        </form>
    @endcan
    @can('attendance.view')
        <x-button variant="ghost" icon="calendar" :href="route('admin.attendance.timesheet', ['employee_id' => $day->employee_id, 'month' => $day->work_date->format('Y-m')])">{{ __('attendance.timesheet.title') }}</x-button>
    @endcan
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2" data-region="day-status">
        @include('admin.attendance._day-status')
        @include('admin.attendance._sources', ['sources' => $day->sources ?? []])
        @foreach (array_diff($day->flags ?? [], ['manual']) as $flag)
            <x-badge tone="warning">{{ __('attendance.flags.'.$flag) }}</x-badge>
        @endforeach
        @foreach ($siblings as $sibling)
            <a href="{{ route('admin.attendance.days.show', $sibling) }}" class="text-label-md text-text-link hover:underline">{{ __('attendance.days.other_shift', ['shift' => $sibling->shift?->code ?? '—']) }}</a>
        @endforeach
    </div>

    @if ($day->isLocked())
        <x-alert tone="info" :title="__('attendance.days.locked_title')">{{ __('attendance.days.locked_help') }}</x-alert>
    @endif

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <x-stat-card :label="__('attendance.fields.scheduled')" :value="Duration::format($day->scheduled_minutes)" icon="calendar" />
        <x-stat-card :label="__('attendance.fields.worked')" :value="Duration::format($day->worked_minutes)" icon="clock" />
        <x-stat-card :label="__('attendance.fields.late')" :value="Duration::format($day->late_minutes)" icon="alert-triangle" />
        <x-stat-card :label="__('attendance.fields.early_leave')" :value="Duration::format($day->early_leave_minutes)" icon="log-out" />
        <x-stat-card :label="__('attendance.fields.break')" :value="Duration::format($day->break_minutes)" icon="minus" />
        <x-stat-card :label="__('attendance.fields.overtime')" :value="Duration::format($day->overtime_minutes)" icon="trend-up" :hint="$day->overtime_minutes ? $day->overtime_status->label() : null" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('attendance.days.schedule')" class="lg:col-span-1">
            <x-detail-list>
                <x-detail-item :label="__('attendance.fields.date')">{{ $day->work_date->translatedFormat('l j F Y') }}</x-detail-item>
                <x-detail-item :label="__('attendance.fields.shift')">
                    @if ($day->shift)
                        {{ $day->shift->code }} · {{ $day->shift->name }}
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ AttendanceClock::localTime($day->scheduled_start_at, $tz) }} – {{ AttendanceClock::localTime($day->scheduled_end_at, $tz) }}@if ($day->shift->crossesMidnight()) (+1)@endif</span>
                    @else
                        {{ $day->is_rest_day ? __('attendance.enums.day_statuses.rest_day') : __('attendance.days.no_shift') }}
                    @endif
                </x-detail-item>
                <x-detail-item :label="__('attendance.days.schedule_source')">{{ __('attendance.enums.schedule_sources.'.($calc['schedule']['source'] ?? 'weekend_rule')) }}</x-detail-item>
                <x-detail-item :label="__('attendance.fields.timezone')"><span dir="ltr">{{ $tz }}</span></x-detail-item>
                @if ($day->holiday)
                    <x-detail-item :label="__('attendance.fields.holiday')">{{ $day->holiday->name }}</x-detail-item>
                @endif
                @if ($day->leave_reference)
                    <x-detail-item :label="__('attendance.fields.leave')"><span dir="ltr">{{ $day->leave_reference }}</span></x-detail-item>
                @endif
                <x-detail-item :label="__('attendance.fields.policy')">
                    @if ($day->policyVersion)
                        {{ $day->policyVersion->policy->code }} · {{ $day->policyVersion->policy->name }} — {{ __('attendance.days.version', ['n' => $day->policyVersion->version]) }}
                    @else
                        {{ __('attendance.days.settings_defaults') }}
                    @endif
                </x-detail-item>
                <x-detail-item :label="__('attendance.fields.calculated_at')"><span dir="ltr">{{ $day->calculated_at?->setTimezone($tz)->format('Y-m-d H:i') }}</span></x-detail-item>
            </x-detail-list>
        </x-card>

        <x-card :title="__('attendance.days.calculation')" :subtitle="__('attendance.days.calculation_help')" class="lg:col-span-2" data-region="calculation">
            @if (! empty($calc['pairs']))
                <ul class="mb-4 flex flex-wrap gap-2">
                    @foreach ($calc['pairs'] as $pair)
                        <li class="rounded-md bg-surface-muted px-2.5 py-1 text-label-md tabular-nums" dir="ltr">
                            {{ AttendanceClock::localTime(\Carbon\CarbonImmutable::parse($pair['in']), $tz) }} → {{ AttendanceClock::localTime(\Carbon\CarbonImmutable::parse($pair['out']), $tz) }}
                            @if (! $pair['in_punch_id'] || ! $pair['out_punch_id'])<span class="text-text-tertiary">*</span>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
            <ol class="flex flex-col divide-y divide-border-subtle">
                @foreach ($calc['steps'] ?? [] as $step)
                    <li class="flex items-start justify-between gap-4 py-2">
                        <span class="text-body-md text-text-secondary">{{ __('attendance.steps.'.$step['key']) }}</span>
                        <span class="text-label-md text-text-primary tabular-nums" dir="ltr">{{ is_bool($step['value']) ? ($step['value'] ? '✓' : '✗') : (is_int($step['value']) && str_ends_with($step['key'], 'minutes') ? Duration::format($step['value'], '0:00') : $step['value']) }}</span>
                    </li>
                @endforeach
            </ol>
            @if ($rules)
                <details class="mt-4 rounded-lg border border-border-subtle p-3">
                    <summary class="cursor-pointer text-label-md text-text-link">{{ __('attendance.days.rules_used') }}</summary>
                    <dl class="mt-3 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                        @foreach ($rules as $rule => $value)
                            <div class="flex justify-between gap-3 border-b border-border-subtle pb-1">
                                <dt class="text-body-sm text-text-secondary">{{ __('schedules.policies.rule_labels.'.$rule) }}</dt>
                                <dd class="text-label-md tabular-nums" dir="ltr">{{ is_bool($value) ? ($value ? '✓' : '✗') : ($value ?? '—') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </details>
            @endif
        </x-card>
    </div>

    @if ($day->overtime_minutes > 0)
        <x-card :title="__('attendance.days.overtime')" data-region="overtime">
            <p class="text-body-md text-text-secondary">
                {{ __('attendance.days.overtime_summary', ['minutes' => Duration::format($day->overtime_minutes), 'status' => $day->overtime_status->label()]) }}
                @if ($day->overtime_approved_minutes !== null)
                    — {{ __('attendance.fields.overtime_approved') }}: <span dir="ltr">{{ Duration::format($day->overtime_approved_minutes, '0:00') }}</span>
                @endif
            </p>
            @can('decideOvertime', $day)
                <form method="POST" action="{{ route('admin.attendance.days.overtime', $day) }}" class="mt-4 flex flex-wrap items-end gap-3" data-validate>
                    @csrf
                    <x-form.input name="minutes" type="number" :label="__('attendance.days.approve_minutes')" :value="old('minutes', $day->overtime_minutes)" min="0" :max="$day->overtime_minutes" class="w-40" />
                    <x-button name="decision" value="approve" icon="check">{{ __('attendance.common.approve') }}</x-button>
                    <x-button name="decision" value="reject" variant="danger" icon="x">{{ __('attendance.common.reject') }}</x-button>
                </form>
            @endcan
        </x-card>
    @endif

    <x-card :title="__('attendance.days.raw_events')" :subtitle="__('attendance.days.raw_events_help')" padding="none" data-region="raw-events">
        <x-table :caption="__('attendance.days.raw_events')">
            <x-slot:head>
                <tr>
                    <x-table.th>#</x-table.th>
                    <x-table.th>{{ __('attendance.fields.time') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.event') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.source') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.reference') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.event_status') }}</x-table.th>
                    <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($events as $event)
                <x-table.row @class(['opacity-60' => $event->isVoided()])>
                    <x-table.td label="#" class="tabular-nums text-text-tertiary">{{ $event->id }}</x-table.td>
                    <x-table.td :label="__('attendance.fields.time')" class="tabular-nums">
                        <span dir="ltr">{{ $event->kind->value === 'punch' ? $event->occurred_at->setTimezone($tz)->format('Y-m-d H:i:s') : $event->work_date?->format('Y-m-d') }}</span>
                    </x-table.td>
                    <x-table.td :label="__('attendance.fields.event')">
                        {{ $event->kind->label() }}:
                        {{ $event->kind->value === 'punch' ? $event->direction?->label() : ($event->attendanceStatus?->name ?? __('attendance.days.mark_cleared')) }}
                        @if ($event->note)
                            <span class="block text-caption-md text-text-tertiary">{{ $event->note }}</span>
                        @endif
                    </x-table.td>
                    <x-table.td :label="__('attendance.fields.source')">
                        @include('admin.attendance._sources', ['sources' => [$event->source]])
                        @if ($event->recorder)
                            <span class="block text-caption-md text-text-tertiary">{{ $event->recorder->name }}</span>
                        @endif
                    </x-table.td>
                    <x-table.td :label="__('attendance.fields.reference')"><span class="text-caption-md" dir="ltr">{{ $event->source_reference ?? '—' }}</span></x-table.td>
                    <x-table.td :label="__('attendance.fields.event_status')">
                        <x-badge :tone="$event->isVoided() ? 'danger' : 'neutral'">{{ $event->status->label() }}</x-badge>
                        @if ($event->void_reason)
                            <span class="block text-caption-md text-text-tertiary">{{ $event->void_reason }}</span>
                        @endif
                    </x-table.td>
                    <x-table.td>
                        @if (! $event->isVoided() && auth()->user()->can('attendance.update') && ! $day->isLocked())
                            <form method="POST" action="{{ route('admin.attendance.events.void', $event) }}" class="flex items-center justify-end gap-2"
                                data-confirm="{{ __('attendance.days.confirm_void') }}" data-confirm-tone="warning" data-confirm-label="{{ __('attendance.days.void') }}">
                                @csrf
                                <x-form.input name="reason" :id="'reason-'.$event->id" :label="__('attendance.fields.reason')" hide-label required maxlength="500" :placeholder="__('attendance.fields.reason')" class="w-36" />
                                <x-icon-button type="submit" icon="x-circle" size="sm" variant="danger" :label="__('attendance.days.void')" />
                            </form>
                        @endif
                    </x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="7" icon="inbox" :title="__('attendance.days.no_events')" />
            @endforelse
        </x-table>
    </x-card>

    <x-card :title="__('attendance.days.punches')" padding="none" data-region="punches">
        <x-table :caption="__('attendance.days.punches')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('attendance.fields.time') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.direction') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.source') }}</x-table.th>
                    <x-table.th>{{ __('attendance.days.used') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($day->punches as $punch)
                <x-table.row>
                    <x-table.td :label="__('attendance.fields.time')" class="tabular-nums"><span dir="ltr">{{ $punch->punched_at->setTimezone($tz)->format('Y-m-d H:i:s') }}</span></x-table.td>
                    <x-table.td :label="__('attendance.fields.direction')">{{ $punch->direction->label() }}</x-table.td>
                    <x-table.td :label="__('attendance.fields.source')">@include('admin.attendance._sources', ['sources' => [$punch->source]])</x-table.td>
                    <x-table.td :label="__('attendance.days.used')">
                        @if ($punch->is_duplicate)
                            <x-badge tone="warning">{{ __('attendance.days.duplicate') }}</x-badge>
                        @elseif (in_array($punch->id, $calc['ignored_punch_ids'] ?? [], true))
                            <x-badge tone="warning">{{ __('attendance.days.ignored') }}</x-badge>
                        @else
                            <x-badge tone="success">{{ __('attendance.days.paired') }}</x-badge>
                        @endif
                    </x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="4" icon="clock" :title="__('attendance.days.no_punches')" />
            @endforelse
        </x-table>
    </x-card>

    @if ($day->adjustments->isNotEmpty())
        <x-card :title="__('attendance.days.adjustments')" :subtitle="__('attendance.days.adjustments_help')" padding="none" data-region="adjustments">
            <x-table :caption="__('attendance.days.adjustments')">
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('attendance.fields.date') }}</x-table.th>
                        <x-table.th>{{ __('attendance.fields.status') }}</x-table.th>
                        <x-table.th>{{ __('attendance.fields.worked') }}</x-table.th>
                        <x-table.th>{{ __('attendance.fields.late') }}</x-table.th>
                        <x-table.th>{{ __('attendance.fields.overtime') }}</x-table.th>
                        <x-table.th>{{ __('attendance.fields.reason') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($day->adjustments as $adjustment)
                    <x-table.row>
                        <x-table.td :label="__('attendance.fields.date')" class="tabular-nums"><span dir="ltr">{{ $adjustment->created_at->setTimezone($tz)->format('Y-m-d H:i') }}</span></x-table.td>
                        <x-table.td :label="__('attendance.fields.status')">{{ __('attendance.enums.day_statuses.'.$adjustment->status_before) }} → {{ $adjustment->status_after === 'removed' ? __('attendance.days.removed') : __('attendance.enums.day_statuses.'.$adjustment->status_after) }}</x-table.td>
                        <x-table.td :label="__('attendance.fields.worked')" class="tabular-nums"><span dir="ltr">{{ Duration::format($adjustment->worked_minutes_delta, '0:00') }}</span></x-table.td>
                        <x-table.td :label="__('attendance.fields.late')" class="tabular-nums"><span dir="ltr">{{ Duration::format($adjustment->late_minutes_delta, '0:00') }}</span></x-table.td>
                        <x-table.td :label="__('attendance.fields.overtime')" class="tabular-nums"><span dir="ltr">{{ Duration::format($adjustment->overtime_minutes_delta, '0:00') }}</span></x-table.td>
                        <x-table.td :label="__('attendance.fields.reason')">{{ $adjustment->creator?->name }}</x-table.td>
                    </x-table.row>
                @endforeach
            </x-table>
        </x-card>
    @endif

    @if ($corrections->isNotEmpty())
        <x-card :title="__('attendance.corrections.title')" padding="none">
            <x-table :caption="__('attendance.corrections.title')">
                <x-slot:head>
                    <tr>
                        <x-table.th>#</x-table.th>
                        <x-table.th>{{ __('attendance.fields.reason') }}</x-table.th>
                        <x-table.th>{{ __('attendance.fields.requested_by') }}</x-table.th>
                        <x-table.th>{{ __('attendance.fields.status') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($corrections as $correction)
                    <x-table.row data-href="{{ route('admin.attendance.corrections.show', $correction) }}">
                        <x-table.td label="#"><a class="text-text-link hover:underline" href="{{ route('admin.attendance.corrections.show', $correction) }}">{{ $correction->id }}</a></x-table.td>
                        <x-table.td :label="__('attendance.fields.reason')">{{ $correction->reason }}</x-table.td>
                        <x-table.td :label="__('attendance.fields.requested_by')">{{ $correction->requester?->name }} · {{ $correction->source->label() }}</x-table.td>
                        <x-table.td :label="__('attendance.fields.status')">@include('admin.attendance.corrections._status')</x-table.td>
                    </x-table.row>
                @endforeach
            </x-table>
        </x-card>
    @endif
@endsection
