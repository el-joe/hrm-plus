{{-- Status badge of a recruitment / lifecycle enum with label() and tone(). Variables: $value --}}
@if ($value)
    <x-badge :tone="$value->tone()" dot>{{ $value->label() }}</x-badge>
@endif
