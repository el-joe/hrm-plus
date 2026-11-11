{{--
    Modal on a native <dialog> (560 wide, radius 16, shadow xl; header/footer 24x16, body 24).
    Props: id, title, size (sm 440 | md 560 | lg 720).
    Slots: default (body), footer.
    Open with any element carrying data-modal-open="{id}"; close with data-modal-close or
    a <form method="dialog">. See resources/js/ui/modal.js.
--}}
@props(['id', 'title' => null, 'size' => 'md'])

@php
    $widths = ['sm' => 'max-w-110', 'md' => 'max-w-140', 'lg' => 'max-w-180'];
@endphp

<dialog id="{{ $id }}" data-modal aria-labelledby="{{ $id }}-title" {{ $attributes->class($widths[$size] ?? $widths['md']) }}>
    <div class="flex max-h-full w-full flex-col overflow-hidden rounded-xl bg-surface-raised text-text-primary shadow-xl">
        <header class="flex items-center justify-between gap-3 border-b border-border-subtle px-6 py-4">
            <h2 id="{{ $id }}-title" class="text-h4">{{ $title }}</h2>
            <x-icon-button icon="x" :label="__('app.ui.close')" size="sm" data-modal-close />
        </header>
        <div class="overflow-y-auto p-6">
            {{ $slot }}
        </div>
        @isset($footer)
            <footer class="flex flex-wrap items-center justify-end gap-3 border-t border-border-subtle px-6 py-4">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</dialog>
