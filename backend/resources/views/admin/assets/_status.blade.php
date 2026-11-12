{{-- Asset status pill. Variables: $status (App\Enums\AssetStatus). --}}
<x-badge :tone="$status->tone()" dot>{{ $status->label() }}</x-badge>
