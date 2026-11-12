{{--
    Dropdown menu on <details> (works without JS; resources/js/ui/dropdown.js closes it on
    outside click, Escape and item activation).
    Props: align (end | start), direction (down | up — up opens above the trigger, e.g. in a bottom bar). Slots: trigger (summary content), default (x-dropdown.item list).
--}}
@props(['align' => 'end', 'direction' => 'down', 'label' => null])

<details {{ $attributes->class('relative') }} data-dropdown>
    <summary class="cursor-pointer list-none rounded-md" @if ($label) aria-label="{{ $label }}" @endif>
        {{ $trigger }}
    </summary>
    <div role="menu" @class([
        'absolute z-50 min-w-56 rounded-lg border border-border-subtle bg-surface-raised p-1.5 shadow-lg',
        'top-full mt-2 overflow-hidden' => $direction !== 'up',
        'bottom-full mb-2 max-h-[70vh] overflow-y-auto' => $direction === 'up',
        'end-0' => $align === 'end',
        'start-0' => $align === 'start',
    ])>
        {{ $slot }}
    </div>
</details>
