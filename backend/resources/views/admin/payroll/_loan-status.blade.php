{{-- Loan status pill. Variables: $loan (EmployeeLoan). --}}
@php($state = ['pending' => 'pending', 'active' => 'approved', 'completed' => 'finalized', 'rejected' => 'rejected', 'cancelled' => 'draft'][$loan->status->value])
<x-status-badge :state="$state">{{ $loan->status->label() }}</x-status-badge>
