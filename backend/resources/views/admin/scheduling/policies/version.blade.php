@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('schedules.policies.title'), 'url' => route('admin.attendance-policies.index')],
    ['label' => $record->name, 'url' => route('admin.attendance-policies.show', $record)],
]])

@section('title', __('schedules.policies.new_version'))

@section('content')
    <form method="POST" action="{{ route('admin.attendance-policies.versions.store', $record) }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf

        <x-form.section :title="__('schedules.policies.new_version')" :description="__('schedules.policies.new_version_help')">
            <x-form.input type="date" name="effective_from" :label="__('schedules.fields.effective_from')" :value="old('effective_from', $effectiveFrom)" required
                :help="$latest ? __('schedules.policies.effective_after', ['date' => $latest->effective_from->format('Y-m-d')]) : null" />
        </x-form.section>

        @include('admin.scheduling.policies._rule-fields')

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.attendance-policies.show', $record)">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
