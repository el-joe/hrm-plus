{{-- Month totals. Variables: $summary (AttendanceTimesheet::summary()). --}}
@php use App\Support\Duration; @endphp
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6" data-region="summary">
    <x-stat-card :label="__('attendance.summary.attended_days')" :value="$summary['present_days'] + $summary['late_days']" icon="check-circle" :hint="__('attendance.summary.late_days', ['count' => $summary['late_days']])" />
    <x-stat-card :label="__('attendance.summary.absent_days')" :value="$summary['absent_days']" icon="x-circle" :hint="__('attendance.summary.incomplete_days', ['count' => $summary['incomplete_days']])" />
    <x-stat-card :label="__('attendance.summary.leave_days')" :value="$summary['leave_days']" icon="calendar" :hint="__('attendance.summary.holidays', ['count' => $summary['holidays']])" />
    <x-stat-card :label="__('attendance.summary.worked_hours')" :value="Duration::format($summary['worked_minutes'], '0:00')" icon="clock" :hint="__('attendance.summary.of_scheduled', ['hours' => Duration::format($summary['scheduled_minutes'], '0:00')])" />
    <x-stat-card :label="__('attendance.summary.late_early')" :value="Duration::format($summary['late_minutes'] + $summary['early_leave_minutes'], '0:00')" icon="alert-triangle" :hint="__('attendance.summary.missing_punches', ['count' => $summary['missing_punches']])" />
    <x-stat-card :label="__('attendance.summary.overtime')" :value="Duration::format($summary['overtime_approved_minutes'], '0:00')" icon="trend-up" :hint="__('attendance.summary.overtime_pending', ['hours' => Duration::format($summary['overtime_pending_minutes'], '0:00')])" />
</div>
