@extends('layouts.admin', ['breadcrumbs' => [['label' => __('performance.competencies.title'), 'url' => route('admin.competencies.index')]]])

@section('title', $record->exists ? __('performance.competencies.edit') : __('performance.competencies.create'))

@section('content')
    <form method="POST" action="{{ $record->exists ? route('admin.competencies.update', $record) : route('admin.competencies.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('performance.competencies.details')">
            @include('admin.structure._identity-fields')
            <x-form.select name="category" :label="__('performance.fields.category')" required>
                @foreach ($categories as $category)
                    <option value="{{ $category->value }}" @selected(old('category', $record->category?->value) === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </x-form.select>
            <div>
                <input type="hidden" name="is_active" value="0">
                <x-form.switch name="is_active" :label="__('structure.common.active')" :checked="(bool) old('is_active', $record->is_active)" />
            </div>
            @include('admin.structure._description-field')
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.competencies.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
