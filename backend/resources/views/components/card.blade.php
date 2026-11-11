{{--
    Generic card: surface/default, border/subtle, radius 16, padding 20, shadow xs.
    Props: title, subtitle, padding (default p-5; pass "none" for flush content such as tables).
    Slots: actions (end side of the header), footer.
--}}
@props(['title' => null, 'subtitle' => null, 'padding' => 'default', 'as' => 'section'])

<{{ $as }} {{ $attributes->class('rounded-xl border border-border-subtle bg-surface-default shadow-xs '.($padding === 'none' ? '' : 'p-5')) }}>
    @if ($title || isset($actions))
        <header @class(['flex flex-wrap items-start justify-between gap-3', 'mb-4' => $padding !== 'none', 'px-5 pt-5 pb-4' => $padding === 'none'])>
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-h5 text-text-primary">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="text-body-sm text-text-tertiary">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    {{ $slot }}

    @isset($footer)
        <footer @class(['mt-4 border-t border-border-subtle pt-4', 'px-5 pb-5' => $padding === 'none'])>{{ $footer }}</footer>
    @endisset
</{{ $as }}>
