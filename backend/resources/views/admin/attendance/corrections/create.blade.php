@php use App\Services\Attendance\AttendanceClock; @endphp
@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('attendance.corrections.title'), 'url' => route('admin.attendance.corrections.index')]],
    'subtitle' => __('attendance.corrections.create_help'),
])

@section('title', __('attendance.corrections.create'))

@section('content')
    @if (! $employee || ! $date)
        <x-card :title="__('attendance.corrections.pick')">
            <form method="GET" action="{{ route('admin.attendance.corrections.create') }}" class="grid gap-4 md:grid-cols-3 md:items-end">
                <x-form.select name="employee_id" :label="__('attendance.fields.employee')" required>
                    <option value="">—</option>
                    @foreach ($employees as $option)
                        <option value="{{ $option->id }}" @selected($employee?->id === $option->id)>{{ $option->label() }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input name="date" type="date" :label="__('attendance.fields.date')" :value="$date" required />
                <div><x-button icon="arrow-right">{{ __('attendance.common.continue') }}</x-button></div>
            </form>
        </x-card>
    @else
        @php $tz = $resolved->timezone; @endphp
        <form method="POST" action="{{ route('admin.attendance.corrections.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
            @csrf
            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
            <input type="hidden" name="work_date" value="{{ $date }}">

            <x-form.section :title="$employee->label()" :description="__('attendance.corrections.for_date', ['date' => \Carbon\Carbon::parse($date)->translatedFormat('l j F Y'), 'timezone' => $tz])">
                <x-form.select name="shift_id" :label="__('attendance.fields.shift')">
                    @forelse ($resolved->shifts as $shift)
                        <option value="{{ $shift->key() }}" @selected((int) old('shift_id', $shiftId) === $shift->key())>{{ $shift->shift->code }} · {{ $shift->shift->timeRange() }}</option>
                    @empty
                        <option value="">{{ $resolved->isRestDay ? __('attendance.enums.day_statuses.rest_day') : __('attendance.days.no_shift') }}</option>
                    @endforelse
                </x-form.select>
                <x-form.select name="attendance_status_id" :label="__('attendance.corrections.set_status')" :help="__('attendance.corrections.set_status_help')">
                    <option value="">{{ __('attendance.corrections.status_from_punches') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}" @selected((string) old('attendance_status_id') === (string) $status->id)>{{ $status->name }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input name="check_in" type="time" :label="__('attendance.fields.check_in')" :value="old('check_in')" :help="__('attendance.corrections.time_help')" />
                <x-form.input name="check_out" type="time" :label="__('attendance.fields.check_out')" :value="old('check_out')" :help="__('attendance.corrections.time_help')" />
                <div class="md:col-span-2">
                    <x-form.textarea name="reason" :label="__('attendance.fields.reason')" required maxlength="1000" rows="3">{{ old('reason') }}</x-form.textarea>
                </div>
            </x-form.section>

            @php $punches = $days->flatMap->punches; @endphp
            @if ($punches->isNotEmpty())
                <x-form.section :title="__('attendance.corrections.remove_punches')" :description="__('attendance.corrections.remove_punches_help')">
                    <div class="flex flex-col gap-2 md:col-span-2">
                        @foreach ($punches as $punch)
                            <x-form.checkbox name="void_raw_event_ids[]" :id="'void-'.$punch->attendance_raw_event_id" :value="$punch->attendance_raw_event_id"
                                :checked="in_array((string) $punch->attendance_raw_event_id, (array) old('void_raw_event_ids', []), true)"
                                :label="$punch->punched_at->setTimezone($tz)->format('Y-m-d H:i').' · '.$punch->direction->label().' · '.$punch->source->label()" />
                        @endforeach
                    </div>
                </x-form.section>
            @endif

            <x-form.actions>
                <x-button variant="secondary" :href="route('admin.attendance.corrections.index')">{{ __('app.actions.cancel') }}</x-button>
                <x-button icon="check">{{ __('attendance.corrections.submit') }}</x-button>
            </x-form.actions>
        </form>
    @endif
@endsection
