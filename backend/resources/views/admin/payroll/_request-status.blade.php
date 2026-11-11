{{-- Status pill of an entry / change (App\Enums\RequestStatus). Variables: $status. --}}
@php($state = ['pending' => 'pending', 'approved' => 'approved', 'rejected' => 'rejected', 'cancelled' => 'draft'][$status->value])
<x-status-badge :state="$state">{{ $status === App\Enums\RequestStatus::Approved ? __('payroll.common.current') : $status->label() }}</x-status-badge>
