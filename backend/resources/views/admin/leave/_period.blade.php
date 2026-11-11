{{-- Dates of a leave request (LTR) plus the partial-day label. Variables: $leave (LeaveRequest). --}}
<span dir="ltr" class="tabular-nums">{{ $leave->dateRange() }}</span>@if ($leave->partLabel()) <span class="text-text-tertiary">· {{ $leave->partLabel() }}</span>@endif
