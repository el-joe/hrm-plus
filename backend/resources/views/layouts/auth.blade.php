{{--
    Layout for sign-in and password pages of both areas.
    Variables: $area (App\Enums\AuthArea) — which area the page belongs to.
    Sections:  title, intro (optional line under the title), content.

    Admin:    600 form panel (padding 64, 400 form column) + fluid brand-strong panel (padding 80).
    Employee: centred 480 card (padding 40, radius 24) on surface/brand.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('app.supported_locales.'.app()->getLocale(), 'rtl') }}">
<head>
    @include('partials.head')
</head>
<body data-layout="auth" data-area="{{ $area->value }}">
    @php
        $isAdminArea = $area === \App\Enums\AuthArea::Admin;
    @endphp

    @if ($isAdminArea)
        <div class="flex min-h-dvh">
            <div class="flex w-full flex-col bg-surface-default px-6 py-8 sm:px-16 sm:py-12 lg:w-150 lg:shrink-0">
                <header class="flex items-center justify-between gap-4">
                    <x-logo />
                    <div class="flex items-center gap-2">
                        @include('partials.theme-toggle')
                        @include('partials.locale-switcher')
                    </div>
                </header>

                <main class="mx-auto flex w-full max-w-100 flex-1 flex-col justify-center gap-6 py-12">
                    <div>
                        <h1 class="text-h2 text-text-primary">@yield('title')</h1>
                        @hasSection('intro')
                            <p class="mt-1 text-body-md text-text-secondary">@yield('intro')</p>
                        @endif
                    </div>

                    @include('partials.auth-messages')

                    @yield('content')

                    <x-alert tone="info">{{ __('app.auth.admin_only_notice') }}</x-alert>
                </main>

                <footer class="text-center text-caption-md text-text-tertiary">{{ __('app.ui.copyright', ['year' => now()->year, 'name' => config('app.name')]) }}</footer>
            </div>

            <aside class="hidden flex-1 flex-col justify-center gap-8 bg-surface-brand-strong p-20 text-text-on-primary lg:flex" aria-hidden="true">
                <span class="w-fit rounded-full bg-surface-brand px-3 py-1 text-label-sm text-text-on-brand-muted">{{ __('app.auth.brand_badge') }}</span>
                <h2 class="max-w-xl text-display-md">{{ __('app.auth.brand_heading') }}</h2>
                <p class="max-w-xl text-body-lg text-text-on-brand-muted">{{ __('app.auth.brand_text') }}</p>
                <ul class="flex flex-col gap-3">
                    @foreach (__('app.auth.brand_points') as $point)
                        <li class="flex items-center gap-3 text-body-md">
                            <x-icon name="check-circle" class="text-primary-300" />
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            </aside>
        </div>
    @else
        <div class="flex min-h-dvh flex-col items-center justify-center gap-6 bg-surface-brand px-4 py-10">
            <main class="w-full max-w-120 rounded-2xl bg-surface-default p-6 shadow-xl sm:p-10">
                <header class="mb-8 flex items-center justify-between gap-4">
                    <x-logo />
                    <div class="flex items-center gap-2">
                        @include('partials.theme-toggle')
                        @include('partials.locale-switcher')
                    </div>
                </header>

                <div class="flex flex-col gap-6">
                    <div>
                        <h1 class="text-h2 text-text-primary">@yield('title')</h1>
                        @hasSection('intro')
                            <p class="mt-1 text-body-md text-text-secondary">@yield('intro')</p>
                        @endif
                    </div>

                    @include('partials.auth-messages')

                    @yield('content')
                </div>
            </main>
            <footer class="text-caption-md text-text-on-brand-muted">{{ __('app.ui.copyright', ['year' => now()->year, 'name' => config('app.name')]) }}</footer>
        </div>
    @endif
</body>
</html>
