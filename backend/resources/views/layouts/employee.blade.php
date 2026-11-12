{{--
    Layout of the employee portal (/portal): top navigation, no sidebar; bottom
    navigation on mobile.

    @extends('layouts.employee', ['hero' => true])  hero: the page renders its own
    brand band in the "hero" section instead of the standard page header.
    Sections: title, page-actions, hero, content. Stack: scripts.
    $navigation comes from the view composer in AppServiceProvider.
--}}
@php
    $user = auth()->user();
    $primaryNavigation = array_values(array_filter($navigation, fn (array $item): bool => ! $item['more']));
    $moreNavigation = array_values(array_filter($navigation, fn (array $item): bool => $item['more']));
    $moreActive = collect($moreNavigation)->contains('active', true);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('app.supported_locales.'.app()->getLocale(), 'rtl') }}" data-digits="{{ \App\Support\Formatter::usesArabicDigits() ? 'arab' : 'latn' }}" data-date-format="{{ \App\Support\Formatter::dateFormat() }}">
<head>
    @include('partials.head')
</head>
<body data-layout="employee" class="pb-18 md:pb-0">
    <a href="#content" class="sr-only z-[70] rounded-md bg-surface-default px-4 py-2 focus:not-sr-only focus:fixed focus:start-4 focus:top-4">{{ __('app.ui.skip_to_content') }}</a>

    <header data-region="topbar" class="sticky top-0 z-30 border-b border-border-subtle bg-surface-default">
        <div class="mx-auto flex h-14 max-w-312 items-center gap-6 px-4 md:h-18 md:px-8 2xl:px-0">
            <a href="{{ route('portal.home') }}" class="rounded-md"><x-logo /></a>

            <nav data-region="navigation" aria-label="{{ __('app.nav.main') }}" class="hidden md:block">
                <ul class="flex items-center gap-1">
                    @foreach ($primaryNavigation as $item)
                        <li>
                            <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                                @class([
                                    'inline-flex h-10 items-center rounded-md px-3 text-label-md transition-colors lg:px-4',
                                    'bg-action-primary-subtle text-text-link' => $item['active'],
                                    'text-text-secondary hover:bg-action-neutral-hover hover:text-text-primary' => ! $item['active'],
                                ])>{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                    @if ($moreNavigation !== [])
                        <li>
                            <x-dropdown align="start" :label="__('app.nav.more')" data-region="portal-more">
                                <x-slot:trigger>
                                    <span @class([
                                        'inline-flex h-10 items-center gap-1 rounded-md px-3 text-label-md transition-colors lg:px-4',
                                        'bg-action-primary-subtle text-text-link' => $moreActive,
                                        'text-text-secondary hover:bg-action-neutral-hover hover:text-text-primary' => ! $moreActive,
                                    ])>{{ __('app.nav.more') }} <x-icon name="chevron-down" size="sm" /></span>
                                </x-slot:trigger>
                                @foreach ($moreNavigation as $item)
                                    <x-dropdown.item :href="$item['url']" :icon="$item['icon']" :aria-current="$item['active'] ? 'page' : null">{{ $item['label'] }}</x-dropdown.item>
                                @endforeach
                            </x-dropdown>
                        </li>
                    @endif
                </ul>
            </nav>

            <div class="ms-auto flex items-center gap-2">
                <div class="hidden sm:block">@include('partials.locale-switcher')</div>
                @include('partials.theme-toggle')
                @include('partials.notification-bell', ['area' => 'portal'])

                <x-dropdown :label="__('app.ui.user_menu')">
                    <x-slot:trigger>
                        <span class="flex items-center gap-2 rounded-md p-1 hover:bg-action-neutral-hover">
                            <x-avatar :name="$user->name" size="sm" />
                            <span class="hidden max-w-40 truncate text-label-md text-text-primary md:block" data-user-name>{{ $user->name }}</span>
                            <x-icon name="chevron-down" size="sm" class="hidden text-text-tertiary md:block" />
                        </span>
                    </x-slot:trigger>

                    <div class="px-3 py-2">
                        <p class="truncate text-label-md">{{ $user->name }}</p>
                        <p class="truncate text-caption-md text-text-tertiary">{{ $user->email }}</p>
                    </div>
                    @if ($user->isAdmin())
                        <x-dropdown.item :href="route('admin.dashboard')" icon="layout-grid">{{ __('app.areas.admin') }}</x-dropdown.item>
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
                    {{-- Only a portal (employee guard) session can be ended here; an admin
                         who came from the admin area signs out there. --}}
                    @auth('employee')
                        <x-dropdown.divider />
                        <x-dropdown.item :action="route('portal.logout')" icon="log-out" tone="danger">{{ __('app.nav.logout') }}</x-dropdown.item>
                    @endauth
                </x-dropdown>
            </div>
        </div>
    </header>

    @hasSection('hero')
        @yield('hero')
    @endif

    <main id="content" data-region="content" class="mx-auto flex w-full max-w-312 flex-col gap-4 px-4 py-4 md:gap-6 md:px-8 md:py-8 2xl:px-0">
        @unless (($hero ?? false) === true)
            <x-page-header :title="html_entity_decode(trim($__env->yieldContent('title')), ENT_QUOTES | ENT_HTML5)" :subtitle="$subtitle ?? null">
                @yield('page-actions')
            </x-page-header>
        @endunless

        @include('partials.flash')

        @yield('content')
    </main>

    {{-- Bottom navigation (mobile). --}}
    <nav aria-label="{{ __('app.nav.main') }}" class="fixed inset-x-0 bottom-0 z-30 h-18 border-t border-border-subtle bg-surface-default md:hidden">
        <ul class="flex h-full items-stretch justify-around">
            @foreach (array_slice($primaryNavigation, 0, 4) as $item)
                <li class="flex-1">
                    <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                        @class([
                            'flex h-full flex-col items-center justify-center gap-1 text-caption-md',
                            'text-text-link' => $item['active'],
                            'text-text-tertiary' => ! $item['active'],
                        ])>
                        <x-icon :name="$item['icon']" />
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
            @php($overflow = [...array_slice($primaryNavigation, 4), ...$moreNavigation])
            @if ($overflow !== [])
                <li class="flex-1">
                    <x-dropdown direction="up" :label="__('app.nav.more')" class="h-full" data-region="portal-more-mobile">
                        <x-slot:trigger>
                            <span @class([
                                'flex h-18 flex-col items-center justify-center gap-1 text-caption-md',
                                'text-text-link' => collect($overflow)->contains('active', true),
                                'text-text-tertiary' => ! collect($overflow)->contains('active', true),
                            ])>
                                <x-icon name="more" />
                                {{ __('app.nav.more') }}
                            </span>
                        </x-slot:trigger>
                        @foreach ($overflow as $item)
                            <x-dropdown.item :href="$item['url']" :icon="$item['icon']" :aria-current="$item['active'] ? 'page' : null">{{ $item['label'] }}</x-dropdown.item>
                        @endforeach
                    </x-dropdown>
                </li>
            @endif
        </ul>
    </nav>

    <x-toast-region offset="top-18 md:top-22" />
    <x-confirm-dialog />

    @stack('scripts')
</body>
</html>
