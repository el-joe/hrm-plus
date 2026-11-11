@extends('layouts.admin', ['breadcrumbs' => [['label' => __('structure.company.title'), 'url' => route('admin.company.show')]]])

@section('title', __('structure.company.edit'))

@section('content')
    <form method="POST" action="{{ route('admin.company.update') }}" enctype="multipart/form-data" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @method('PUT')

        <x-form.section :title="__('structure.company.identity')" :description="__('structure.company.identity_help')">
            <x-form.input name="name_ar" :label="__('structure.fields.name_ar')" :value="old('name_ar', $company->name_ar)" required maxlength="150" dir="rtl" lang="ar" />
            <x-form.input name="name_en" :label="__('structure.fields.name_en')" :value="old('name_en', $company->name_en)" required maxlength="150" dir="ltr" lang="en" />
        </x-form.section>

        <x-form.section :title="__('structure.company.legal')" :description="__('structure.company.legal_help')">
            <x-form.input name="legal_name_ar" :label="__('structure.fields.legal_name_ar')" :value="old('legal_name_ar', $company->legal_name_ar)" maxlength="200" dir="rtl" lang="ar" />
            <x-form.input name="legal_name_en" :label="__('structure.fields.legal_name_en')" :value="old('legal_name_en', $company->legal_name_en)" maxlength="200" dir="ltr" lang="en" />
            <x-form.input name="commercial_registration_no" :label="__('structure.fields.commercial_registration_no')" :value="old('commercial_registration_no', $company->commercial_registration_no)" maxlength="50" dir="ltr" />
            <x-form.input name="tax_number" :label="__('structure.fields.tax_number')" :value="old('tax_number', $company->tax_number)" maxlength="50" dir="ltr" />
            <x-form.input name="founded_on" type="date" :label="__('structure.fields.founded_on')" :value="old('founded_on', $company->founded_on?->format('Y-m-d'))" max="{{ now()->format('Y-m-d') }}" />
        </x-form.section>

        <x-form.section :title="__('structure.company.contact')" :description="__('structure.company.contact_help')">
            <x-form.input name="email" type="email" :label="__('structure.fields.email')" :value="old('email', $company->email)" maxlength="150" dir="ltr" />
            <x-form.input name="phone" type="tel" :label="__('structure.fields.phone')" :value="old('phone', $company->phone)" maxlength="30" dir="ltr" />
            <x-form.input name="website" type="url" :label="__('structure.fields.website')" :value="old('website', $company->website)" maxlength="200" dir="ltr" placeholder="https://" />
            <x-form.input name="country_code" :label="__('structure.fields.country_code')" :value="old('country_code', $company->country_code)" minlength="2" maxlength="2" dir="ltr" class="uppercase"
                :help="__('structure.branches.country_help')" />
            <x-form.textarea name="address_ar" :label="__('structure.fields.address_ar')" maxlength="500" rows="2" dir="rtl" lang="ar">{{ old('address_ar', $company->address_ar) }}</x-form.textarea>
            <x-form.textarea name="address_en" :label="__('structure.fields.address_en')" maxlength="500" rows="2" dir="ltr" lang="en">{{ old('address_en', $company->address_en) }}</x-form.textarea>
        </x-form.section>

        <x-form.section :title="__('structure.company.branding')" :description="__('structure.company.branding_help')">
            <div class="flex items-center gap-4 md:col-span-2">
                <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border-subtle bg-surface-subtle">
                    @if ($company->logoUrl())
                        <img src="{{ $company->logoUrl() }}" alt="{{ __('structure.company.logo_alt', ['name' => $company->name]) }}" class="max-h-full max-w-full object-contain">
                    @else
                        <x-icon name="image" size="lg" class="text-text-tertiary" />
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <x-form.file-input name="logo" :label="__('structure.fields.logo')" accept="image/png,image/jpeg,image/webp" />
                </div>
            </div>
            @if ($company->logo_path)
                <div class="md:col-span-2">
                    <x-form.checkbox name="remove_logo" value="1" :label="__('structure.fields.remove_logo')" :checked="(bool) old('remove_logo')" />
                </div>
            @else
                <p class="text-body-sm text-text-tertiary md:col-span-2">{{ __('structure.company.no_logo') }}</p>
            @endif
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.company.show')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
