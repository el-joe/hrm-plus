{{--
    Product mark + name. Props: subtitle (bool), tone (default | inverse, for dark panels).
--}}
@props(['subtitle' => true, 'tone' => 'default'])

<span {{ $attributes->class('inline-flex items-center gap-3') }}>
    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-action-primary-default text-text-on-primary">
        <x-icon name="users" />
    </span>
    <span @class(['flex min-w-0 flex-col', 'text-sidebar-text-active' => $tone === 'inverse', 'text-text-primary' => $tone !== 'inverse']) data-sidebar-label>
        <span class="truncate text-h5">{{ config('app.name') }}</span>
        @if ($subtitle)
            <span @class(['truncate text-caption-md', 'text-sidebar-text-muted' => $tone === 'inverse', 'text-text-tertiary' => $tone !== 'inverse'])>{{ __('app.ui.tagline') }}</span>
        @endif
    </span>
</span>
