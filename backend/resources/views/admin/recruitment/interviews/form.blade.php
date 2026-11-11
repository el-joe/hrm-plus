@extends('layouts.admin', [
    'breadcrumbs' => [
        ['label' => __('recruitment.vacancies.title'), 'url' => route('admin.vacancies.index')],
        ['label' => $application->vacancy->title, 'url' => route('admin.vacancies.show', $application->vacancy)],
        ['label' => $application->candidate->name, 'url' => route('admin.applications.show', $application)],
    ],
])

@section('title', $interview->exists ? __('recruitment.interviews.edit') : __('recruitment.interviews.schedule'))

@section('content')
    @php
        $start = $interview->exists ? $interview->localStart() : null;
        $selected = collect(old('interviewer_ids', $selectedInterviewers))->map(fn ($id) => (string) $id)->all();
    @endphp
    <form method="POST" action="{{ $interview->exists ? route('admin.interviews.update', $interview) : route('admin.applications.interviews.store', $application) }}"
        class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($interview->exists)
            @method('PUT')
        @endif
        <x-form.section :title="__('recruitment.interviews.section_when')" :description="__('recruitment.interviews.section_when_help')">
            <x-form.select name="type" :label="__('recruitment.fields.type')" required>
                @foreach ($options['types'] as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $interview->type?->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="duration_minutes" type="number" min="10" max="480" step="5" :label="__('recruitment.fields.duration_minutes')" :value="old('duration_minutes', $interview->duration_minutes)" required />
            <x-form.input name="date" type="date" :label="__('recruitment.fields.date')" :value="old('date', $start?->toDateString())" required />
            <x-form.input name="time" type="time" :label="__('recruitment.fields.time')" :value="old('time', $start?->format('H:i'))" required />
            <x-form.select name="timezone" :label="__('recruitment.fields.timezone')" required>
                @foreach ($options['timezones'] as $zone)
                    <option value="{{ $zone }}" @selected(old('timezone', $interview->timezone ?: $options['defaultTimezone']) === $zone)>{{ $zone }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="mode" :label="__('recruitment.fields.mode')" required>
                @foreach ($options['modes'] as $mode)
                    <option value="{{ $mode->value }}" @selected(old('mode', $interview->mode?->value) === $mode->value)>{{ $mode->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="location" :label="__('recruitment.fields.location')" :value="old('location', $interview->location)" maxlength="255" :help="__('recruitment.interviews.location_help')" />
            <x-form.input name="meeting_url" type="url" :label="__('recruitment.fields.meeting_url')" :value="old('meeting_url', $interview->meeting_url)" maxlength="500" dir="ltr" :help="__('recruitment.interviews.link_help')" />
        </x-form.section>

        <x-form.section :title="__('recruitment.interviews.section_panel')" :description="__('recruitment.interviews.section_panel_help')">
            <div class="md:col-span-2">
                <x-form.field :label="__('recruitment.fields.interviewers')" :message="$errors->first('interviewer_ids') ?: $errors->first('interviewer_ids.*')" required>
                    <div class="grid max-h-72 gap-2 overflow-y-auto rounded-md border border-border-default p-3 sm:grid-cols-2">
                        @foreach ($options['interviewers'] as $person)
                            <x-form.checkbox name="interviewer_ids[]" :value="$person->id" :id="'interviewer-'.$person->id" :label="$person->pickerLabel()"
                                :description="$person->isEmployee() ? __('recruitment.interviews.employee_account') : __('recruitment.interviews.admin_account')"
                                :checked="in_array((string) $person->id, $selected, true)" />
                        @endforeach
                    </div>
                </x-form.field>
            </div>
            <x-form.select name="evaluation_form_id" :label="__('recruitment.fields.evaluation_form_id')">
                <option value="">{{ __('recruitment.common.none') }}</option>
                @foreach ($options['evaluationForms'] as $form)
                    <option value="{{ $form->id }}" @selected((string) old('evaluation_form_id', $interview->evaluation_form_id) === (string) $form->id)>{{ $form->name }}</option>
                @endforeach
            </x-form.select>
            <div class="flex items-end pb-2">
                <input type="hidden" name="notify_candidate" value="0">
                <x-form.switch name="notify_candidate" :label="__('recruitment.interviews.notify_candidate')" :checked="(bool) old('notify_candidate', true)" />
            </div>
            <div class="md:col-span-2">
                <x-form.textarea name="notes" :label="__('recruitment.fields.notes')" rows="3" maxlength="2000">{{ old('notes', $interview->notes) }}</x-form.textarea>
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="$interview->exists ? route('admin.interviews.show', $interview) : route('admin.applications.show', $application)">{{ __('app.actions.cancel') }}</x-button>
            <x-button icon="calendar">{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
