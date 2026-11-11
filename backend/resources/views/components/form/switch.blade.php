{{--
    Toggle (a checkbox with role="switch"). Props: name, value, label, id, description.
    Pass :checked="..." and any other input attribute directly.
--}}
@props(['name' => null, 'value' => '1', 'label' => null, 'id' => null, 'description' => null])

@php
    $id ??= trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', ($name ?? 'switch').'-'.$value), '-');
@endphp

<label for="{{ $id }}" class="inline-flex cursor-pointer items-start gap-3 has-disabled:cursor-not-allowed has-disabled:opacity-60">
    <input id="{{ $id }}" type="checkbox" role="switch" name="{{ $name }}" value="{{ $value }}" {{ $attributes->class('peer sr-only') }}>
    <span aria-hidden="true"
        class="mt-0.5 flex h-5 w-9 shrink-0 items-center rounded-full bg-border-strong p-0.5 transition-colors peer-checked:justify-end peer-checked:bg-action-primary-default peer-focus-visible:shadow-focus">
        <span class="size-4 rounded-full bg-neutral-0 shadow-sm"></span>
    </span>
    @if ($label)
        <span class="text-body-md text-text-primary">
            {{ $label }}
            @if ($description)
                <span class="block text-caption-md text-text-tertiary">{{ $description }}</span>
            @endif
        </span>
    @endif
</label>
