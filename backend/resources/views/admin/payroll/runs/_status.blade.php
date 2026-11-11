{{-- Payroll run status pill. Variable: $run. --}}
<x-status-badge :state="$run->status->badge()">{{ $run->status->label() }}</x-status-badge>
