{{--
    Filter bar: a GET form (padding 16, gap 12, radius 12, controls 40 high).
    Props: action, reset (URL that clears the filters). Slot: the x-form.* controls (use hide-label
    or short labels). resources/js/ui/filters.js auto-submits selects and debounced search fields and
    drops empty parameters; the explicit Filter button keeps it working without JS.
--}}
@props(['action', 'reset' => null])

<form method="GET" action="{{ $action }}" role="search" data-filters
    {{ $attributes->class('flex flex-col gap-3 rounded-lg border border-border-subtle bg-surface-default p-4 shadow-xs md:flex-row md:flex-wrap md:items-end') }}>
    {{ $slot }}

    @foreach (['sort', 'direction'] as $keep)
        @if (request()->filled($keep))
            <input type="hidden" name="{{ $keep }}" value="{{ request()->query($keep) }}">
        @endif
    @endforeach

    <div class="flex items-center gap-2 md:ms-auto">
        @if ($reset)
            <x-button variant="ghost" :href="$reset">{{ __('app.actions.reset') }}</x-button>
        @endif
        <x-button variant="secondary" icon="filter" data-filters-submit>{{ __('app.actions.filter') }}</x-button>
    </div>
</form>
