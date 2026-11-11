{{--
    Native select with label, help and error. The slot holds the <option> elements.
    Props: as x-form.input (no type/icon).
--}}
@props(['name' => null, 'label' => null, 'id' => null, 'help' => null, 'error' => null, 'errorKeys' => [], 'hideLabel' => false])

@php
    $id ??= $name ? trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-') : null;
    $errorKey = $error ?? ($name ? trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.') : null);
    $message = collect([$errorKey, ...$errorKeys])->filter()->map(fn (string $key) => $errors->first($key))->filter()->first();
@endphp

<x-form.field :label="$label" :for="$id" :message="$message" :help="$help" :required="(bool) $attributes->get('required')" :hide-label="$hideLabel">
    <div class="relative">
        <select id="{{ $id }}" name="{{ $name }}" aria-describedby="{{ trim(($help ? $id.'-help ' : '').$id.'-error') }}"
            @if ($message) aria-invalid="true" @endif
            {{ $attributes->class([
                'h-10 w-full appearance-none rounded-md border border-border-default bg-surface-default ps-3 pe-9 text-body-md text-text-primary shadow-xs transition',
                'focus:border-border-focus focus:shadow-focus focus:outline-none',
                'disabled:cursor-not-allowed disabled:bg-surface-subtle disabled:text-text-disabled',
                'aria-invalid:border-feedback-danger-solid aria-invalid:focus:shadow-focus-danger',
            ]) }}>
            {{ $slot }}
        </select>
        <x-icon name="chevron-down" size="sm" class="pointer-events-none absolute end-3 top-1/2 -translate-y-1/2 text-text-tertiary" />
    </div>
</x-form.field>
