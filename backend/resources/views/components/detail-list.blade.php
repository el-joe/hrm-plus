{{-- Read-only label / value grid for show pages. Slot: x-detail-item elements. Props: columns (1 | 2). --}}
@props(['columns' => 2])

<dl {{ $attributes->class(['grid gap-x-6 gap-y-4', 'sm:grid-cols-2' => (int) $columns === 2]) }}>
    {{ $slot }}
</dl>
