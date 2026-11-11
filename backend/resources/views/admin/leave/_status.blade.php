{{-- Leave request status pill. Variables: $leave (LeaveRequest). --}}
<x-status-badge :state="$leave->status->badge()">{{ $leave->status->label() }}</x-status-badge>
