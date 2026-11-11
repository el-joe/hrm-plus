{{--
    File uploader (native input, styled). Props: as x-form.input (no type/icon).
--}}
@props(['name' => null, 'label' => null, 'id' => null, 'help' => null, 'error' => null, 'errorKeys' => [], 'hideLabel' => false])

@php
    $id ??= $name ? trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-') : null;
    $errorKey = $error ?? ($name ? trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.') : null);
    $message = collect([$errorKey, ...$errorKeys])->filter()->map(fn (string $key) => $errors->first($key))->filter()->first();
@endphp

<x-form.field :label="$label" :for="$id" :message="$message" :help="$help" :required="(bool) $attributes->get('required')" :hide-label="$hideLabel">
    <input id="{{ $id }}" name="{{ $name }}" type="file" aria-describedby="{{ trim(($help ? $id.'-help ' : '').$id.'-error') }}"
        @if ($message) aria-invalid="true" @endif
        {{ $attributes->class([
            'w-full cursor-pointer rounded-lg border border-dashed border-border-strong bg-surface-subtle p-3 text-body-sm text-text-secondary',
            'file:me-3 file:h-8 file:cursor-pointer file:rounded-md file:border-0 file:bg-action-primary-subtle file:px-3 file:text-label-sm file:text-text-link hover:file:bg-action-primary-subtle-hover',
            'focus:border-border-focus focus:shadow-focus focus:outline-none aria-invalid:border-feedback-danger-solid',
        ]) }}>
</x-form.field>
