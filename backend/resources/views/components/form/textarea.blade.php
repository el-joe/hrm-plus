{{--
    Multi-line input. Same props as x-form.input (no type/icon). The slot is the value.
--}}
@props(['name' => null, 'label' => null, 'id' => null, 'help' => null, 'error' => null, 'errorKeys' => [], 'hideLabel' => false])

@php
    $id ??= $name ? trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-') : null;
    $errorKey = $error ?? ($name ? trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.') : null);
    $message = collect([$errorKey, ...$errorKeys])->filter()->map(fn (string $key) => $errors->first($key))->filter()->first();
@endphp

<x-form.field :label="$label" :for="$id" :message="$message" :help="$help" :required="(bool) $attributes->get('required')" :hide-label="$hideLabel">
    <textarea id="{{ $id }}" name="{{ $name }}" aria-describedby="{{ trim(($help ? $id.'-help ' : '').$id.'-error') }}"
        @if ($message) aria-invalid="true" @endif
        {{ $attributes->merge(['rows' => 4])->class([
            'min-h-24 w-full rounded-md border border-border-default bg-surface-default px-3 py-2 text-body-md text-text-primary shadow-xs transition',
            'placeholder:text-text-disabled focus:border-border-focus focus:shadow-focus focus:outline-none',
            'disabled:cursor-not-allowed disabled:bg-surface-subtle disabled:text-text-disabled',
            'aria-invalid:border-feedback-danger-solid aria-invalid:focus:shadow-focus-danger',
        ]) }}>{{ $slot }}</textarea>
</x-form.field>
