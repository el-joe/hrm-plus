{{-- Account status pill. Variables: $status (App\Enums\UserStatus). --}}
<x-badge :tone="['active' => 'success', 'inactive' => 'neutral', 'suspended' => 'danger'][$status->value] ?? 'neutral'" dot
    data-badge="status" data-status="{{ $status->value }}">{{ $status->label() }}</x-badge>
