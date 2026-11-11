{{--
    Language switch: one button per other supported locale, posting to locale.update.
    Works without JavaScript. Props via @include: $variant ('button' default | 'menu').
--}}
@foreach (array_keys(config('app.supported_locales')) as $locale)
    @continue($locale === app()->getLocale())
    <form method="POST" action="{{ route('locale.update') }}" data-locale-switcher>
        @csrf
        <input type="hidden" name="locale" value="{{ $locale }}">
        <button type="submit" lang="{{ $locale }}" title="{{ __('app.ui.switch_language', [], $locale) }}"
            class="inline-flex h-10 items-center gap-2 rounded-md border border-border-default bg-surface-default px-3 text-label-md text-text-primary transition-colors hover:bg-action-neutral-hover">
            <x-icon name="globe" size="sm" class="text-text-tertiary" />
            <span>{{ __('app.locales.'.$locale) }}</span>
        </button>
    </form>
@endforeach
