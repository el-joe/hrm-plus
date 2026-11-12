@extends('layouts.admin', ['breadcrumbs' => [['label' => __('assets.title'), 'url' => route('admin.assets.index')]]])

@section('title', $asset->exists ? __('assets.edit').' · '.$asset->asset_tag : __('assets.create'))

@section('content')
    <form method="POST" action="{{ $asset->exists ? route('admin.assets.update', $asset) : route('admin.assets.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($asset->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('assets.sections.identity')" :description="__('assets.sections.identity_help')">
            <x-form.input name="asset_tag" :label="__('assets.fields.asset_tag')" :value="old('asset_tag', $asset->asset_tag)" required maxlength="40" dir="ltr" class="uppercase" />
            <x-form.select name="asset_category_id" :label="__('assets.fields.asset_category_id')" required>
                <option value="">—</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('asset_category_id', $asset->asset_category_id) === (string) $category->id)>{{ $category->name }}@if ($category->requires_serial) *@endif</option>
                @endforeach
            </x-form.select>
            <div class="md:col-span-2">
                <x-form.input name="name" :label="__('assets.fields.name')" :value="old('name', $asset->name)" required maxlength="200" />
            </div>
            <x-form.input name="brand" :label="__('assets.fields.brand')" :value="old('brand', $asset->brand)" maxlength="100" />
            <x-form.input name="model" :label="__('assets.fields.model')" :value="old('model', $asset->model)" maxlength="100" />
            <x-form.input name="serial_number" :label="__('assets.fields.serial_number')" :value="old('serial_number', $asset->serial_number)" maxlength="100" dir="ltr" />
        </x-form.section>

        <x-form.section :title="__('assets.sections.purchase')" :description="__('assets.sections.purchase_help')">
            <x-form.input type="date" name="purchase_date" :label="__('assets.fields.purchase_date')" :value="old('purchase_date', $asset->purchase_date?->toDateString())" :max="now()->toDateString()" />
            <x-form.input type="number" name="purchase_cost" step="0.01" min="0" dir="ltr" :label="__('assets.fields.purchase_cost').' ('.setting('localisation.currency_code').')'" :value="old('purchase_cost', $asset->purchase_cost)" />
            <x-form.input name="supplier" :label="__('assets.fields.supplier')" :value="old('supplier', $asset->supplier)" maxlength="150" />
            <x-form.input type="date" name="warranty_expires_on" :label="__('assets.fields.warranty_expires_on')" :value="old('warranty_expires_on', $asset->warranty_expires_on?->toDateString())" />
        </x-form.section>

        <x-form.section :title="__('assets.sections.location')" :description="__('assets.sections.location_help')">
            <x-form.select name="branch_id" :label="__('assets.fields.branch_id')">
                <option value="">—</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $asset->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </x-form.select>
            @if ($asset->exists)
                <x-form.field :label="__('assets.fields.condition')">
                    <div>@include('admin.assets._condition', ['condition' => $asset->condition])</div>
                </x-form.field>
            @else
                <x-form.select name="condition" :label="__('assets.fields.condition')" required>
                    @foreach ($conditions as $condition)
                        <option value="{{ $condition->value }}" @selected(old('condition', $asset->condition?->value) === $condition->value)>{{ $condition->label() }}</option>
                    @endforeach
                </x-form.select>
            @endif
            <div class="md:col-span-2">
                <x-form.textarea name="notes" :label="__('assets.fields.notes')" rows="3" maxlength="2000">{{ old('notes', $asset->notes) }}</x-form.textarea>
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="$asset->exists ? route('admin.assets.show', $asset) : route('admin.assets.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
