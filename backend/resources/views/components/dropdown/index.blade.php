{{--
    Dropdown menu on <details> (works without JS; resources/js/ui/dropdown.js closes it on
    outside click, Escape and item activation).
    Props: align (end | start). Slots: trigger (summary content), default (x-dropdown.item list).
--}}
@props(['align' => 'end', 'label' => null])

<details {{ $attributes->class('relative') }} data-dropdown>
    <summary class="cursor-pointer list-none rounded-md" @if ($label) aria-label="{{ $label }}" @endif>
        {{ $trigger }}
    </summary>
    <div role="menu" @class([
        'absolute top-full z-50 mt-2 min-w-56 overflow-hidden rounded-lg border border-border-subtle bg-surface-raised p-1.5 shadow-lg',
        'end-0' => $align === 'end',
        'start-0' => $align === 'start',
    ])>
        {{ $slot }}
    </div>
</details>
