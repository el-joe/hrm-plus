{{-- Action type + status badges. Variables: $action (App\Models\DisciplinaryAction). --}}
<span class="inline-flex flex-wrap items-center gap-1">
    <x-badge :tone="$action->type->tone()">{{ $action->type->label() }}</x-badge>
    @if ($action->status !== App\Enums\DisciplinaryActionStatus::Approved)
        <x-badge :tone="$action->status->tone()">{{ $action->status->label() }}</x-badge>
    @endif
</span>
