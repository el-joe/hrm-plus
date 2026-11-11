{{-- One label / value pair inside x-detail-list. Props: label. Slot: value. --}}
@props(['label'])

<div {{ $attributes->class('flex min-w-0 flex-col gap-0.5') }}>
    <dt class="text-label-sm text-text-tertiary">{{ $label }}</dt>
    <dd class="break-words text-body-md text-text-primary">{{ $slot }}</dd>
</div>
