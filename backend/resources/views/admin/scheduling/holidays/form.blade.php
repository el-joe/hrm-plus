@extends('layouts.admin', ['breadcrumbs' => [['label' => __('schedules.holidays.title'), 'url' => route('admin.holidays.index')]]])

@section('title', $record->exists ? __('schedules.holidays.edit') : __('schedules.holidays.create'))

@section('content')
    <form method="POST" action="{{ $record->exists ? route('admin.holidays.update', $record) : route('admin.holidays.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('schedules.fields.name')">
            @include('admin.structure._identity-fields', ['withCode' => false])
        </x-form.section>

        <x-form.section :title="__('schedules.holidays.dates')" :description="__('schedules.holidays.dates_help')">
            <x-form.input type="date" name="start_date" :label="__('schedules.fields.start_date')" :value="old('start_date', $record->start_date?->format('Y-m-d'))" required />
            <x-form.input type="date" name="end_date" :label="__('schedules.fields.end_date')" :value="old('end_date', $record->end_date?->format('Y-m-d'))" />
            <div>
                <input type="hidden" name="is_recurring" value="0">
                <x-form.switch name="is_recurring" :label="__('schedules.fields.is_recurring')" :checked="(bool) old('is_recurring', $record->is_recurring)" />
            </div>
            <div>
                <input type="hidden" name="is_paid" value="0">
                <x-form.switch name="is_paid" :label="__('schedules.fields.is_paid')" :checked="(bool) old('is_paid', $record->is_paid)" />
            </div>
        </x-form.section>

        <x-form.section :title="__('schedules.holidays.scope')">
            <x-form.select name="branch_id" :label="__('schedules.fields.branch_id')" class="md:col-span-2">
                <option value="">{{ __('schedules.common.all_branches') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $record->branch_id) === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
                @endforeach
            </x-form.select>
            @include('admin.structure._description-field')
            @include('admin.structure._active-switch')
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.holidays.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
