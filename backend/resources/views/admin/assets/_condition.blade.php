{{-- Asset condition badge. Variables: $condition (App\Enums\AssetCondition|null). --}}
@if ($condition)
    <x-badge :tone="$condition->tone()">{{ $condition->label() }}</x-badge>
@else
    <span class="text-text-tertiary">—</span>
@endif
