{{-- HR request priority pill. Variables: $priority (App\Enums\HrRequestPriority). --}}
<x-badge :tone="$priority->tone()">{{ $priority->label() }}</x-badge>
