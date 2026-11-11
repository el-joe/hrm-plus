{{--
    Global search shell of the admin top bar.
    - With JavaScript (resources/js/ui/global-search.js): jumps to any page in the
      admin's sidebar, plus "search admin users" when allowed.
    - Without JavaScript: a plain GET form to the admin users list when the admin
      may view it; otherwise the field is not shown at all.
    Later modules (employees, ...) point the fallback action at their own search.
--}}
@php
    $searchAction = auth()->user()->can('admin_users.view') ? route('admin.admin-users.index') : null;
@endphp

<form method="GET" action="{{ $searchAction ?? '#' }}" role="search" data-global-search
    @if ($searchAction) data-search-action="{{ $searchAction }}" @else hidden data-js-only @endif
    class="relative hidden w-90 max-w-full md:block">
    <label for="global-search" class="sr-only">{{ __('app.ui.search_label') }}</label>
    <x-icon name="search" size="sm" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-text-tertiary" />
    <input id="global-search" type="search" name="search" autocomplete="off" placeholder="{{ __('app.ui.search_placeholder') }}"
        role="combobox" aria-expanded="false" aria-controls="global-search-results" aria-autocomplete="list"
        class="h-10 w-full rounded-md border border-border-default bg-surface-subtle ps-9 pe-3 text-body-md text-text-primary placeholder:text-text-tertiary focus:border-border-focus focus:bg-surface-default focus:shadow-focus focus:outline-none"
        data-global-search-input>
    <ul id="global-search-results" role="listbox" hidden data-global-search-results
        class="absolute inset-x-0 top-full z-50 mt-2 max-h-80 overflow-y-auto rounded-lg border border-border-subtle bg-surface-raised p-1.5 shadow-lg"></ul>
    <template data-global-search-strings
        data-no-results="{{ __('app.ui.search_no_results') }}"
        data-search-admins="{{ __('app.ui.search_admin_users') }}"></template>
</form>
