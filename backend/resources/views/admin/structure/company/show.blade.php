@extends('layouts.admin', ['subtitle' => __('structure.company.subtitle')])

@section('title', __('structure.company.title'))

@section('page-actions')
    @can('update', $company)
        <x-button :href="route('admin.company.edit')" icon="pencil">{{ __('structure.company.edit') }}</x-button>
    @endcan
@endsection

@section('content')
    @unless ($company->exists)
        <x-alert tone="info">{{ __('structure.company.not_saved') }}</x-alert>
    @endunless

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="__('structure.company.stats.branches')" :value="$counts['branches']" icon="map-pin"
            :href="auth()->user()->can('branches.view') ? route('admin.branches.index') : null" />
        <x-stat-card :label="__('structure.company.stats.departments')" :value="$counts['departments']" icon="layers"
            :href="auth()->user()->can('departments.view') ? route('admin.departments.index') : null" />
        <x-stat-card :label="__('structure.company.stats.positions')" :value="$counts['positions']" icon="briefcase"
            :href="auth()->user()->can('positions.view') ? route('admin.positions.index') : null" />
        <x-stat-card :label="__('structure.company.stats.headcount')" :value="$counts['headcount']" icon="users" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('structure.company.identity')" class="lg:col-span-2">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                <div class="flex size-28 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border-subtle bg-surface-subtle">
                    @if ($company->logoUrl())
                        <img src="{{ $company->logoUrl() }}" alt="{{ __('structure.company.logo_alt', ['name' => $company->name]) }}" class="max-h-full max-w-full object-contain">
                    @else
                        <x-icon name="building" size="lg" class="text-text-tertiary" />
                    @endif
                </div>
                <x-detail-list class="flex-1">
                    <x-detail-item :label="__('structure.fields.name_ar')"><span lang="ar" dir="rtl">{{ $company->name_ar }}</span></x-detail-item>
                    <x-detail-item :label="__('structure.fields.name_en')"><span lang="en" dir="ltr">{{ $company->name_en }}</span></x-detail-item>
                    <x-detail-item :label="__('structure.fields.legal_name_ar')">{{ $company->legal_name_ar ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('structure.fields.legal_name_en')"><span dir="ltr">{{ $company->legal_name_en ?? '—' }}</span></x-detail-item>
                    <x-detail-item :label="__('structure.fields.commercial_registration_no')"><span dir="ltr">{{ $company->commercial_registration_no ?? '—' }}</span></x-detail-item>
                    <x-detail-item :label="__('structure.fields.tax_number')"><span dir="ltr">{{ $company->tax_number ?? '—' }}</span></x-detail-item>
                    <x-detail-item :label="__('structure.fields.founded_on')">{{ $company->founded_on?->format(setting('localisation.date_format')) ?? '—' }}</x-detail-item>
                </x-detail-list>
            </div>
        </x-card>

        <x-card :title="__('structure.company.localisation')" :subtitle="__('structure.company.localisation_help')">
            <x-detail-list :columns="1">
                <x-detail-item :label="__('structure.company.currency')"><span dir="ltr">{{ $localisation['currency_code'] }}</span></x-detail-item>
                <x-detail-item :label="__('structure.fields.timezone')"><span dir="ltr">{{ $localisation['timezone'] }}</span></x-detail-item>
                <x-detail-item :label="__('structure.company.default_language')">{{ __('app.locales.'.$localisation['default_locale']) }}</x-detail-item>
                <x-detail-item :label="__('structure.company.date_format')"><span dir="ltr">{{ $localisation['date_format'] }}</span></x-detail-item>
            </x-detail-list>
            @can('settings.view')
                <x-slot:footer>
                    <x-button variant="ghost" size="sm" :href="route('admin.settings.edit', 'localisation')" icon="settings">{{ __('structure.company.open_settings') }}</x-button>
                </x-slot:footer>
            @endcan
        </x-card>
    </div>

    <x-card :title="__('structure.company.contact')">
        <x-detail-list>
            <x-detail-item :label="__('structure.fields.email')"><span dir="ltr">{{ $company->email ?? '—' }}</span></x-detail-item>
            <x-detail-item :label="__('structure.fields.phone')"><span dir="ltr">{{ $company->phone ?? '—' }}</span></x-detail-item>
            <x-detail-item :label="__('structure.fields.website')">
                @if ($company->website)
                    <a href="{{ $company->website }}" class="text-text-link hover:underline" dir="ltr" rel="noopener noreferrer" target="_blank">{{ $company->website }}</a>
                @else
                    —
                @endif
            </x-detail-item>
            <x-detail-item :label="__('structure.fields.country_code')">{{ $company->country_code ?? '—' }}</x-detail-item>
            <x-detail-item :label="__('structure.fields.address')">{{ $company->localized('address') ?? '—' }}</x-detail-item>
            <x-detail-item :label="__('structure.company.headquarters')">
                @if ($headquarters)
                    @can('view', $headquarters)
                        <a href="{{ route('admin.branches.show', $headquarters) }}" class="text-text-link hover:underline">{{ $headquarters->name }}</a>
                    @else
                        {{ $headquarters->name }}
                    @endcan
                @else
                    {{ __('structure.company.no_headquarters') }}
                @endif
            </x-detail-item>
        </x-detail-list>
    </x-card>
@endsection
