@extends('layouts.admin', ['breadcrumbs' => [['label' => __('schedules.assignments.title'), 'url' => route('admin.schedule-assignments.index')]]])

@section('title', $record->exists ? __('schedules.assignments.edit') : __('schedules.assignments.create'))

@section('content')
    <form method="POST" action="{{ $record->exists ? route('admin.schedule-assignments.update', $record) : route('admin.schedule-assignments.store') }}"
        class="flex flex-col gap-4 lg:gap-6" data-validate data-scope-form>
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('schedules.assignments.target')" :description="__('schedules.assignments.precedence')">
            @include('admin.scheduling._scope-fields', ['currentType' => $record->scope_type?->value, 'currentId' => $record->scope_id])
        </x-form.section>

        <x-form.section :title="__('schedules.assignments.period')">
            <x-form.select name="work_schedule_id" :label="__('schedules.fields.work_schedule_id')" required class="md:col-span-2">
                <option value="">—</option>
                @foreach ($schedules as $schedule)
                    <option value="{{ $schedule->id }}" @selected((string) old('work_schedule_id', $record->work_schedule_id) === (string) $schedule->id)>{{ $schedule->code }} · {{ $schedule->name }} ({{ $schedule->type->label() }})</option>
                @endforeach
            </x-form.select>
            <x-form.input type="date" name="effective_from" :label="__('schedules.fields.effective_from')" :value="old('effective_from', $record->effective_from?->format('Y-m-d'))" required />
            <x-form.input type="date" name="effective_to" :label="__('schedules.fields.effective_to')" :value="old('effective_to', $record->effective_to?->format('Y-m-d'))" />
            <div class="md:col-span-2">
                <x-form.textarea name="note" :label="__('schedules.fields.note')" rows="2" maxlength="500">{{ old('note', $record->note) }}</x-form.textarea>
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.schedule-assignments.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
