{{--
    Text-like input (text, email, password, number, date, search, ...) with label, help and error.
    Props: name, label, id (defaults from name), type, help, icon (leading), error (error-bag key,
           defaults from name: "scopes[branch]" -> "scopes.branch"), errorKeys (extra keys to show),
           hideLabel.
    Everything else (value, required, maxlength, autocomplete, ...) goes on the <input>.
--}}
@props([
    'name' => null,
    'label' => null,
    'id' => null,
    'type' => 'text',
    'help' => null,
    'icon' => null,
    'error' => null,
    'errorKeys' => [],
    'hideLabel' => false,
])

@php
    $id ??= $name ? trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-') : null;
    $errorKey = $error ?? ($name ? trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.') : null);
    $message = collect([$errorKey, ...$errorKeys])->filter()->map(fn (string $key) => $errors->first($key))->filter()->first();
    $describedBy = trim(($help ? $id.'-help ' : '').$id.'-error');
@endphp

<x-form.field :label="$label" :for="$id" :message="$message" :help="$help" :required="(bool) $attributes->get('required')" :hide-label="$hideLabel">
    <div class="relative">
        @if ($icon)
            <x-icon :name="$icon" size="sm" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-text-tertiary" />
        @endif
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" aria-describedby="{{ $describedBy }}"
            @if ($message) aria-invalid="true" @endif
            {{ $attributes->class([
                'h-10 w-full rounded-md border border-border-default bg-surface-default px-3 text-body-md text-text-primary shadow-xs transition',
                'placeholder:text-text-disabled focus:border-border-focus focus:shadow-focus focus:outline-none',
                'disabled:cursor-not-allowed disabled:bg-surface-subtle disabled:text-text-disabled',
                'aria-invalid:border-feedback-danger-solid aria-invalid:focus:shadow-focus-danger',
                'ps-9' => $icon,
            ]) }}>
    </div>
</x-form.field>
