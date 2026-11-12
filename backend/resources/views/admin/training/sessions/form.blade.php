@extends('layouts.admin', ['breadcrumbs' => [['label' => __('training.sessions.title'), 'url' => route('admin.training-sessions.index')]]])

@section('title', $session->exists ? __('training.sessions.edit', ['reference' => $session->reference]) : __('training.sessions.create'))

@section('content')
    <form method="POST" action="{{ $session->exists ? route('admin.training-sessions.update', $session) : route('admin.training-sessions.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($session->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('training.sessions.course_and_dates')">
            <div class="md:col-span-2">
                <x-form.select name="training_course_id" :label="__('training.fields.course')" required>
                    <option value="">—</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected((string) old('training_course_id', $session->training_course_id) === (string) $course->id)>{{ $course->code }} · {{ $course->name }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <x-form.input type="date" name="start_date" required :label="__('training.fields.start_date')" :value="old('start_date', $session->start_date?->toDateString())" />
            <x-form.input type="date" name="end_date" required :label="__('training.fields.end_date')" :value="old('end_date', $session->end_date?->toDateString())" />
            <x-form.input type="time" name="start_time" :label="__('training.fields.start_time')" :value="old('start_time', $session->start_time ? substr((string) $session->start_time, 0, 5) : null)" />
            <x-form.input type="time" name="end_time" :label="__('training.fields.end_time')" :value="old('end_time', $session->end_time ? substr((string) $session->end_time, 0, 5) : null)" />
        </x-form.section>

        <x-form.section :title="__('training.sessions.place_and_seats')" :description="__('training.sessions.place_help')">
            <x-form.select name="delivery_mode" :label="__('training.fields.delivery_mode')" required data-switch>
                @foreach ($modes as $mode)
                    <option value="{{ $mode->value }}" @selected(old('delivery_mode', $session->delivery_mode?->value) === $mode->value)>{{ $mode->label() }}</option>
                @endforeach
            </x-form.select>
            <div data-show-for="classroom blended">
                <x-form.input name="location" :label="__('training.fields.location')" :value="old('location', $session->location)" maxlength="255" />
            </div>
            <div data-show-for="online blended">
                <x-form.input type="url" name="meeting_url" dir="ltr" :label="__('training.fields.meeting_url')" :value="old('meeting_url', $session->meeting_url)" maxlength="500" />
            </div>
            <x-form.input type="number" name="capacity" min="1" max="1000" required :label="__('training.fields.capacity')" :value="old('capacity', $session->capacity)" />
            <x-form.input name="trainer" :label="__('training.fields.trainer')" :value="old('trainer', $session->trainer)" maxlength="150" />
            <x-form.input type="number" name="cost_per_participant" step="0.01" min="0" dir="ltr" :label="__('training.fields.cost_per_participant')" :value="old('cost_per_participant', $session->cost_per_participant)" :help="__('training.sessions.cost_help', ['currency' => $currency])" />
            <x-form.select name="branch_id" :label="__('training.fields.branch')" :help="__('training.sessions.branch_help')">
                <option value="">{{ __('training.sessions.all_branches') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $session->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </x-form.select>
            <div class="md:col-span-2">
                <x-form.textarea name="notes" :label="__('training.fields.notes')" maxlength="2000" rows="3">{{ old('notes', $session->notes) }}</x-form.textarea>
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="$session->exists ? route('admin.training-sessions.show', $session) : route('admin.training-sessions.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
