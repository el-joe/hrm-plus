{{--
    Label + control + help + error wrapper used by every form control.
    Props: label, for (control id), message (error text, already resolved), help, required, hideLabel.
    The error <p> is always rendered (hidden when empty) so client-side
    validation (resources/js/ui/forms.js) can write into it.
--}}
@props(['label' => null, 'for' => null, 'message' => null, 'help' => null, 'required' => false, 'hideLabel' => false])

<div {{ $attributes->class('flex min-w-0 flex-col gap-1.5') }} data-field>
    @if ($label)
        <label for="{{ $for }}" @class(['text-label-md text-text-primary', 'sr-only' => $hideLabel])>
            {{ $label }}
            @if ($required)
                <span class="text-feedback-danger-text" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($help)
        <p id="{{ $for }}-help" class="text-caption-md text-text-tertiary" data-field-help>{{ $help }}</p>
    @endif

    <p id="{{ $for }}-error" class="flex items-center gap-1 text-caption-md text-feedback-danger-text" data-field-error @if (! $message) hidden @endif>{{ $message }}</p>
</div>
