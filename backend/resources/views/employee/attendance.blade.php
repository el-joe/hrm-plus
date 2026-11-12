@php use App\Services\Attendance\AttendanceClock; use App\Support\Duration; @endphp
@extends('layouts.employee')

@section('title', __('attendance.portal.title'))

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-h4 text-text-primary">{{ $from->translatedFormat('F Y') }}</h2>
        <div class="flex gap-2">
            <x-button variant="ghost" size="sm" icon="chevron-left" :href="route('portal.attendance', ['month' => $from->subMonth()->format('Y-m')])">{{ __('attendance.common.previous') }}</x-button>
            @if ($from->addMonth()->lte($today))
                <x-button variant="ghost" size="sm" icon-end="chevron-right" :href="route('portal.attendance', ['month' => $from->addMonth()->format('Y-m')])">{{ __('attendance.common.next') }}</x-button>
            @endif
        </div>
    </div>

    @php $checkInTone = ['checked_in' => 'success', 'checked_out' => 'neutral', 'not_checked_in' => 'warning'][$checkIn['state']] ?? 'neutral'; @endphp
    <x-card :title="__('mobile.web.title')" class="min-w-0" data-region="my-check-in">
        <x-slot:actions>
            <x-badge :tone="$checkInTone" dot data-attendance-state="{{ $checkIn['state'] }}">{{ __('app.home.states.'.$checkIn['state']) }}</x-badge>
        </x-slot:actions>
        @include('employee._check-in', ['checkInId' => 'attendance-check-in'])
    </x-card>

    @include('admin.attendance._summary')

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('attendance.portal.daily_records')" padding="none" class="lg:col-span-2" data-region="my-days">
            <ul class="divide-y divide-border-subtle">
                @forelse ($rows as $row)
                    @php $tz = $row['schedule']->timezone; @endphp
                    @foreach ($row['days'] ?: [null] as $day)
                        <li class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:gap-4">
                            <div class="w-28 shrink-0 text-label-md text-text-primary">{{ $row['date']->translatedFormat('D j M') }}</div>
                            @if ($day)
                                <div class="flex-1 text-body-sm text-text-secondary">
                                    <span class="text-label-md text-text-primary">{{ $day->shift?->code }}</span>
                                    <span dir="ltr" class="tabular-nums">{{ AttendanceClock::localTime($day->first_in_at, $tz) ?? '—' }} → {{ AttendanceClock::localTime($day->last_out_at, $tz) ?? '—' }}</span>
                                    · {{ __('attendance.fields.worked') }} <span dir="ltr" class="tabular-nums">{{ Duration::format($day->worked_minutes, '0:00') }}</span>
                                    @if ($day->late_minutes)· {{ __('attendance.fields.late') }} <span dir="ltr">{{ Duration::format($day->late_minutes) }}</span>@endif
                                    @if ($day->overtime_minutes)· {{ __('attendance.fields.overtime') }} <span dir="ltr">{{ Duration::format($day->overtime_minutes) }}</span>@endif
                                    @foreach (array_intersect($day->flags ?? [], ['missing_check_in', 'missing_check_out']) as $flag)
                                        <x-badge tone="warning">{{ __('attendance.flags.'.$flag) }}</x-badge>
                                    @endforeach
                                </div>
                                <div>@include('admin.attendance._day-status')</div>
                            @else
                                <div class="flex-1 text-body-sm text-text-tertiary">
                                    {{ $row['schedule']->isEmployed() ? ($row['schedule']->isRestDay ? __('attendance.enums.day_statuses.rest_day') : __('attendance.timesheet.not_processed')) : __('attendance.enums.schedule_sources.not_employed') }}
                                </div>
                            @endif
                        </li>
                    @endforeach
                @empty
                    <li class="px-5 py-6"><x-empty-state icon="clock" :title="__('attendance.portal.no_records')" /></li>
                @endforelse
            </ul>
        </x-card>

        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('attendance.portal.my_schedule')" data-region="my-schedule">
                <ul class="flex flex-col gap-2">
                    @foreach ($upcoming as $date => $schedule)
                        <li class="flex items-center justify-between gap-3 text-body-sm">
                            <span class="text-text-secondary">{{ \Carbon\Carbon::parse($date)->translatedFormat('D j M') }}</span>
                            <span class="text-end">
                                @if ($schedule->holiday)
                                    <x-badge tone="info">{{ $schedule->holiday->name }}</x-badge>
                                @endif
                                @forelse ($schedule->shifts as $shift)
                                    <span class="block text-label-md" dir="ltr">{{ $shift->shift->code }} · {{ $shift->shift->timeRange() }}</span>
                                @empty
                                    <span class="text-text-tertiary">{{ __('attendance.enums.day_statuses.rest_day') }}</span>
                                @endforelse
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <x-card :title="__('attendance.portal.request_correction')" :subtitle="__('attendance.portal.request_help')" data-region="correction-form">
                <form method="POST" action="{{ route('portal.attendance.corrections.store') }}" class="flex flex-col gap-3" data-validate>
                    @csrf
                    <x-form.input name="work_date" type="date" :label="__('attendance.fields.date')" :value="old('work_date', $today->format('Y-m-d'))" :max="$today->format('Y-m-d')" required />
                    <div class="grid grid-cols-2 gap-3">
                        <x-form.input name="check_in" type="time" :label="__('attendance.fields.check_in')" :value="old('check_in')" />
                        <x-form.input name="check_out" type="time" :label="__('attendance.fields.check_out')" :value="old('check_out')" />
                    </div>
                    @if ($statuses->isNotEmpty())
                        <x-form.select name="attendance_status_id" :label="__('attendance.portal.or_status')">
                            <option value="">—</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}" @selected((string) old('attendance_status_id') === (string) $status->id)>{{ $status->name }}</option>
                            @endforeach
                        </x-form.select>
                    @endif
                    <x-form.textarea name="reason" :label="__('attendance.fields.reason')" required rows="2" maxlength="1000">{{ old('reason') }}</x-form.textarea>
                    <x-button icon="check">{{ __('attendance.portal.send_request') }}</x-button>
                </form>
            </x-card>

            <x-card :title="__('attendance.portal.my_requests')" padding="none" data-region="my-corrections">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($corrections as $correction)
                        <li class="flex flex-col gap-1 px-5 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <x-date :value="$correction->work_date" class="text-label-md" />
                                @include('admin.attendance.corrections._status')
                            </div>
                            @include('admin.attendance.corrections._requested', ['timezone' => app(\App\Services\Attendance\AttendanceClock::class)->timezoneForBranch($employee->branch_id)])
                            <p class="text-caption-md text-text-tertiary">{{ $correction->reason }}</p>
                            @if ($correction->isPending())
                                <form method="POST" action="{{ route('portal.attendance.corrections.cancel', $correction->id) }}" data-confirm="{{ __('attendance.portal.confirm_withdraw') }}">
                                    @csrf
                                    <x-button variant="ghost" size="sm">{{ __('attendance.portal.withdraw') }}</x-button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-4 text-body-sm text-text-tertiary">{{ __('attendance.portal.no_requests') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
@endsection
