{{-- HR request status pill. Variables: $status (App\Enums\HrRequestStatus). --}}
<x-badge :tone="$status->tone()" dot>{{ $status->label() }}</x-badge>
