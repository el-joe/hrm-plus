{{--
    Empty result / nothing-yet message. Props: icon, title, description. Slot: optional actions.
--}}
@props(['icon' => 'inbox', 'title' => null, 'description' => null])

<div {{ $attributes->class('flex flex-col items-center justify-center gap-3 px-6 py-12 text-center') }}>
    <span class="inline-flex size-12 items-center justify-center rounded-full bg-surface-muted text-text-tertiary">
        <x-icon :name="$icon" size="lg" />
    </span>
    <div class="max-w-sm">
        <p class="text-h5 text-text-primary">{{ $title ?? __('app.common.no_results') }}</p>
        @if ($description)
            <p class="mt-1 text-body-sm text-text-tertiary">{{ $description }}</p>
        @endif
    </div>
    @if (! $slot->isEmpty())
        <div class="flex flex-wrap items-center justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
