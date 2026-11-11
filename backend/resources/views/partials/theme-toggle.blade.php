{{-- Light / dark toggle. Needs JavaScript, so it is hidden until resources/js/ui/theme.js runs. --}}
<button type="button" data-theme-toggle hidden data-js-only
    aria-label="{{ __('app.ui.toggle_theme') }}" title="{{ __('app.ui.toggle_theme') }}"
    class="inline-flex size-10 items-center justify-center rounded-md text-text-secondary transition-colors hover:bg-action-neutral-hover hover:text-text-primary">
    <x-icon name="moon" class="dark:hidden" />
    <x-icon name="sun" class="hidden dark:block" />
</button>
