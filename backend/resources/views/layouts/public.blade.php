{{--
    Public pages (career page): no account, no navigation of the app.
    Sections: title, content. Company identity from $company (nullable).
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('app.supported_locales.'.app()->getLocale(), 'rtl') }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-dvh bg-surface-page text-text-primary" data-layout="public">
    <header class="border-b border-border-subtle bg-surface-default">
        <div class="mx-auto flex max-w-312 items-center justify-between gap-4 px-4 py-3 md:px-8">
            <a href="{{ route('careers.index') }}" class="inline-flex min-w-0 items-center gap-3">
                <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-action-primary-default text-text-on-primary"><x-icon name="briefcase" /></span>
                <span class="min-w-0">
                    <span class="block truncate text-label-lg">{{ $company?->name ?? config('app.name') }}</span>
                    <span class="block text-caption-md text-text-tertiary">{{ __('recruitment.career.tagline') }}</span>
                </span>
            </a>
            <div class="flex items-center gap-2">
                @include('partials.theme-toggle')
                @include('partials.locale-switcher')
            </div>
        </div>
    </header>
    <main class="mx-auto flex max-w-312 flex-col gap-6 px-4 py-8 md:px-8">
        @include('partials.flash')
        @yield('content')
    </main>
    <footer class="py-6 text-center text-caption-md text-text-tertiary">{{ __('app.ui.copyright', ['year' => now()->year, 'name' => $company?->name ?? config('app.name')]) }}</footer>
    <x-toast-region />
    <x-confirm-dialog />
</body>
</html>
