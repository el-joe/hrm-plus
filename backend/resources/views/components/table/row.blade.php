{{-- Body row with hover / selected states. Props: selected. --}}
@props(['selected' => false])

<tr @if ($selected) aria-selected="true" @endif {{ $attributes->class('transition-colors hover:bg-surface-subtle aria-selected:bg-action-primary-subtle') }}>{{ $slot }}</tr>
