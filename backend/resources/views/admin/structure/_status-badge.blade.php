{{-- Active / inactive badge. Variables: $active (bool). --}}
<x-badge :tone="$active ? 'success' : 'neutral'" dot>{{ $active ? __('structure.common.active') : __('structure.common.inactive') }}</x-badge>
