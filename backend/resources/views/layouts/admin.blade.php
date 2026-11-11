{{--
    Layout of the admin dashboard (/admin).

    @extends('layouts.admin', ['breadcrumbs' => [...], 'subtitle' => '...'])
      breadcrumbs: list of ['label' => .., 'url' => ..] between "Dashboard" and the current page
                   (the current page title is appended automatically).
      subtitle:    optional line under the title.
      pageHeader:  false to hide the standard page header (the page renders its own).
    Sections: title, page-actions, content. Stack: scripts.

    $navigation comes from the view composer in AppServiceProvider and only holds
    entries the admin may open; the routes enforce the same permissions.
--}}
@php
    $admin = auth()->user();
    $pageTitle = html_entity_decode(trim($__env->yieldContent('title')), ENT_QUOTES | ENT_HTML5);
    $trail = request()->routeIs('admin.dashboard') ? [] : [
        ['label' => __('app.nav.dashboard'), 'url' => route('admin.dashboard')],
        ...($breadcrumbs ?? []),
        ['label' => $pageTitle, 'url' => null],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('app.supported_locales.'.app()->getLocale(), 'rtl') }}">
<head>
    @include('partials.head')
</head>
<body data-layout="admin">
    <a href="#content" class="sr-only z-[70] rounded-md bg-surface-default px-4 py-2 focus:not-sr-only focus:fixed focus:start-4 focus:top-4">{{ __('app.ui.skip_to_content') }}</a>

    {{-- Scrim behind the off-canvas sidebar (below lg). --}}
    <a href="#" data-sidebar-scrim class="fixed inset-0 z-40 hidden bg-surface-scrim lg:hidden" tabindex="-1" aria-hidden="true"></a>

    <aside id="admin-sidebar" data-admin-sidebar aria-label="{{ __('app.nav.main') }}"
        class="fixed inset-y-0 start-0 z-50 flex w-68 flex-col border-e border-sidebar-border bg-sidebar-bg">
        <div class="flex h-16 shrink-0 items-center justify-between gap-2 px-4">
            <a href="{{ route('admin.dashboard') }}" class="min-w-0 rounded-md">
                <x-logo tone="inverse" />
            </a>
            <a href="#" class="inline-flex size-9 items-center justify-center rounded-md text-sidebar-text hover:bg-sidebar-item-hover lg:hidden" data-sidebar-close aria-label="{{ __('app.ui.close_menu') }}">
                <x-icon name="x" />
            </a>
        </div>

        <nav class="flex-1 overflow-y-auto px-4 py-2" data-sidebar-nav>
            <div class="flex flex-col gap-3">
                @foreach ($navigation as $section)
                    <div class="flex flex-col gap-1" data-nav-section="{{ $section['key'] }}">
                        <p class="px-3 pb-1 text-caption-md text-sidebar-text-muted" data-sidebar-label>{{ $section['label'] }}</p>
                        <ul class="flex flex-col gap-1">
                            @foreach ($section['items'] as $item)
                                <li>
                                    <a href="{{ $item['url'] }}" title="{{ $item['label'] }}" data-sidebar-item data-nav-item="{{ $item['key'] }}"
                                        @if ($item['active']) aria-current="page" @endif
                                        @class([
                                            'flex h-11 items-center gap-3 rounded-md px-3 text-label-md transition-colors',
                                            'bg-sidebar-item-active text-sidebar-text-active' => $item['active'],
                                            'text-sidebar-text hover:bg-sidebar-item-hover hover:text-sidebar-text-active' => ! $item['active'],
                                        ])>
                                        <x-icon :name="$item['icon']" />
                                        <span class="truncate" data-sidebar-label>{{ $item['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>

        <div class="shrink-0 border-t border-sidebar-border p-4">
            <div class="flex items-center gap-3 rounded-lg bg-sidebar-item-hover p-2" data-sidebar-item>
                <x-avatar :name="$admin->name" size="sm" />
                <div class="min-w-0 flex-1" data-sidebar-label>
                    <p class="truncate text-label-md text-sidebar-text-active">{{ $admin->name }}</p>
                    <p class="truncate text-caption-md text-sidebar-text-muted">{{ $admin->roles->first()?->displayName() ?? __('app.account_types.admin') }}</p>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}" data-sidebar-label>
                    @csrf
                    <button type="submit" class="inline-flex size-8 items-center justify-center rounded-md text-sidebar-text hover:bg-sidebar-bg hover:text-sidebar-text-active"
                        aria-label="{{ __('app.nav.logout') }}" title="{{ __('app.nav.logout') }}">
                        <x-icon name="log-out" size="sm" flip />
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div data-admin-main class="flex min-h-dvh flex-col transition-[padding] duration-200 lg:ps-68">
        <header data-region="topbar" class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-border-subtle bg-surface-default px-4 lg:h-16 lg:px-6">
            <a href="#admin-sidebar" class="inline-flex size-10 items-center justify-center rounded-md text-text-secondary hover:bg-action-neutral-hover lg:hidden"
                data-sidebar-open aria-controls="admin-sidebar" aria-expanded="false" aria-label="{{ __('app.ui.open_menu') }}">
                <x-icon name="menu" />
            </a>
            <button type="button" class="hidden size-10 items-center justify-center rounded-md text-text-secondary hover:bg-action-neutral-hover lg:inline-flex"
                data-sidebar-collapse aria-controls="admin-sidebar" aria-label="{{ __('app.ui.toggle_sidebar') }}" title="{{ __('app.ui.toggle_sidebar') }}" hidden data-js-only>
                <x-icon name="panel" data-collapse-icon flip />
            </button>

            @include('partials.global-search')

            <div class="ms-auto flex items-center gap-2">
                <div class="hidden sm:block">@include('partials.locale-switcher')</div>
                @include('partials.theme-toggle')

                <span class="mx-1 hidden h-6 w-px bg-border-default sm:block" aria-hidden="true"></span>

                <x-dropdown :label="__('app.ui.user_menu')">
                    <x-slot:trigger>
                        <span class="flex items-center gap-2 rounded-md p-1 hover:bg-action-neutral-hover">
                            <x-avatar :name="$admin->name" size="sm" />
                            <span class="hidden min-w-0 text-start md:block">
                                <span class="block max-w-40 truncate text-label-md text-text-primary" data-user-name>{{ $admin->name }}</span>
                                <span class="block max-w-40 truncate text-caption-md text-text-tertiary">{{ $admin->email }}</span>
                            </span>
                            <x-icon name="chevron-down" size="sm" class="hidden text-text-tertiary md:block" />
                        </span>
                    </x-slot:trigger>

                    <div class="px-3 py-2 md:hidden">
                        <p class="truncate text-label-md">{{ $admin->name }}</p>
                        <p class="truncate text-caption-md text-text-tertiary">{{ $admin->email }}</p>
                    </div>
                    @can('view', $admin)
                        <x-dropdown.item :href="route('admin.admin-users.show', $admin)" icon="user">{{ __('app.ui.my_account') }}</x-dropdown.item>
                    @endcan
                    @if ($admin->canAccessPortal())
                        <x-dropdown.item :href="route('portal.home')" icon="external">{{ __('app.nav.my_portal') }}</x-dropdown.item>
                    @endif
                    <div class="sm:hidden">
                        @foreach (array_keys(config('app.supported_locales')) as $locale)
                            @continue($locale === app()->getLocale())
                            <x-dropdown.item :action="route('locale.update')" icon="globe">
                                <input type="hidden" name="locale" value="{{ $locale }}">
                                {{ __('app.locales.'.$locale) }}
                            </x-dropdown.item>
                        @endforeach
                    </div>
                    <x-dropdown.divider />
                    <x-dropdown.item :action="route('admin.logout')" icon="log-out" tone="danger">{{ __('app.nav.logout') }}</x-dropdown.item>
                </x-dropdown>
            </div>
        </header>

        <main id="content" data-region="content" class="mx-auto flex w-full max-w-320 flex-1 flex-col gap-4 p-4 lg:gap-6 lg:p-8">
            @unless (($pageHeader ?? true) === false)
                <x-page-header :title="$pageTitle" :subtitle="$subtitle ?? null" :breadcrumbs="$trail">
                    @yield('page-actions')
                </x-page-header>
            @endunless

            @include('partials.flash')

            @yield('content')
        </main>
    </div>

    <x-toast-region offset="top-18 lg:top-20" />
    <x-confirm-dialog />

    @stack('scripts')
</body>
</html>
