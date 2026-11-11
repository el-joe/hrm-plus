{{--
    Page header: breadcrumbs, 12 gap, title (heading/h2) with subtitle, actions on the end side.
    Props: title, subtitle, breadcrumbs (see x-breadcrumbs). Slot: actions.
--}}
@props(['title', 'subtitle' => null, 'breadcrumbs' => []])

<header {{ $attributes->class('flex flex-col gap-3') }} data-page-header>
    <x-breadcrumbs :items="$breadcrumbs" />
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-h3 text-text-primary sm:text-h2">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-1 text-body-md text-text-secondary">{{ $subtitle }}</p>
            @endif
        </div>
        @if (! $slot->isEmpty())
            <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
        @endif
    </div>
</header>
