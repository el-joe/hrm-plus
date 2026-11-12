{{-- Case status pill. Variables: $status (App\Enums\DisciplinaryCaseStatus). --}}
<x-badge :tone="$status->tone()" dot>{{ $status->label() }}</x-badge>
