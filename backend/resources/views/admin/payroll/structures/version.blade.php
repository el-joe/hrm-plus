@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('payroll.structures.title'), 'url' => route('admin.salary-structures.index')],
    ['label' => $record->name, 'url' => route('admin.salary-structures.show', $record)],
]])

@section('title', __('payroll.structures.new_version_title', ['name' => $record->name]))

@section('content')
    <form method="POST" action="{{ route('admin.salary-structures.versions.store', $record) }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        <x-form.section :title="__('payroll.structures.lines')" :description="__('payroll.help.version', ['version' => ($latest?->version ?? 0) + 1])">
            <x-form.input type="date" name="effective_from" required :label="__('payroll.fields.effective_from')" :value="old('effective_from', $effectiveFrom)"
                :help="$latest ? __('payroll.validation.version_after', ['date' => $latest->effective_from->toDateString()]) : null" />
            <x-form.input name="notes" maxlength="1000" :label="__('payroll.fields.notes')" :value="old('notes')" />
            @include('admin.payroll.structures._lines')
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.salary-structures.show', $record)">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
