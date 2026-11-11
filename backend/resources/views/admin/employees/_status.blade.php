{{-- Employee status badge. Variables: $status (App\Enums\EmployeeStatus). --}}
<x-badge :tone="$status->tone()" dot>{{ $status->label() }}</x-badge>
