{{--
    Checkbox with inline label. Props: name, value, label, id, description, type (checkbox | radio).
    Pass :checked="..." and any other input attribute directly.
--}}
@props(['name' => null, 'value' => '1', 'label' => null, 'id' => null, 'description' => null, 'type' => 'checkbox'])

@php
    $id ??= trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', ($name ?? 'check').'-'.$value), '-');
@endphp

<div class="flex items-start gap-2.5">
    <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $value }}"
        {{ $attributes->class([
            'mt-0.5 size-4.5 shrink-0 cursor-pointer border border-border-strong bg-surface-default accent-action-primary-default',
            'focus-visible:shadow-focus disabled:cursor-not-allowed',
            'rounded-xs' => $type === 'checkbox',
            'rounded-full' => $type === 'radio',
        ]) }}>
    @if ($label || ! $slot->isEmpty())
        <label for="{{ $id }}" class="cursor-pointer text-body-md text-text-primary">
            {{ $label ?? $slot }}
            @if ($description)
                <span class="block text-caption-md text-text-tertiary">{{ $description }}</span>
            @endif
        </label>
    @endif
</div>
