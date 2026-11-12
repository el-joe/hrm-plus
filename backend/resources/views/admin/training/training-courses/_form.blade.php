<x-form.section :title="__('training.courses.identity')">
    @include('admin.structure._identity-fields')
    <x-form.select name="training_programme_id" :label="__('training.fields.programme')">
        <option value="">—</option>
        @foreach ($programmes as $programme)
            <option value="{{ $programme->id }}" @selected((string) old('training_programme_id', $record->training_programme_id) === (string) $programme->id)>{{ $programme->name }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="training_provider_id" :label="__('training.fields.provider')">
        <option value="">—</option>
        @foreach ($providers as $provider)
            <option value="{{ $provider->id }}" @selected((string) old('training_provider_id', $record->training_provider_id) === (string) $provider->id)>{{ $provider->name }}</option>
        @endforeach
    </x-form.select>
    <div class="md:col-span-2">
        <x-form.textarea name="description" :label="__('training.fields.description')" maxlength="2000" rows="3">{{ old('description', $record->description) }}</x-form.textarea>
    </div>
</x-form.section>

<x-form.section :title="__('training.courses.delivery')" :description="__('training.courses.delivery_help')">
    <x-form.select name="delivery_mode" :label="__('training.fields.delivery_mode')" required>
        @foreach ($modes as $mode)
            <option value="{{ $mode->value }}" @selected(old('delivery_mode', $record->delivery_mode?->value) === $mode->value)>{{ $mode->label() }}</option>
        @endforeach
    </x-form.select>
    <x-form.input type="number" name="duration_hours" step="0.25" min="0" max="9999" dir="ltr" required :label="__('training.fields.duration_hours')" :value="old('duration_hours', $record->duration_hours)" />
    <x-form.input type="number" name="cost_per_participant" step="0.01" min="0" dir="ltr" required :label="__('training.fields.cost_per_participant')" :value="old('cost_per_participant', $record->cost_per_participant)" :help="setting('localisation.currency_code')" />
    <div class="md:col-span-2">
        <input type="hidden" name="issues_certificate" value="0">
        <x-form.switch name="issues_certificate" :label="__('training.fields.issues_certificate')" :description="__('training.courses.certificate_help')" :checked="(bool) old('issues_certificate', $record->issues_certificate)" />
    </div>
    <x-form.input type="number" name="certificate_validity_months" min="1" max="240" :label="__('training.fields.certificate_validity_months')" :value="old('certificate_validity_months', $record->certificate_validity_months)" :help="__('training.courses.validity_help')" />
    @include('admin.structure._active-switch')
</x-form.section>

<x-form.section :title="__('training.courses.skills')" :description="__('training.courses.skills_help')">
    @php($skillRows = old('skills', $skillRows))
    @php($skillRows = array_values([...$skillRows, ...array_fill(0, max(0, 2 - count($skillRows)), [])]))
    <div class="md:col-span-2 flex flex-col gap-3" data-repeater="skills">
        @error('skills')
            <x-alert tone="danger">{{ $message }}</x-alert>
        @enderror
        <div class="flex flex-col gap-3" data-repeater-rows>
            @foreach ($skillRows as $index => $row)
                @include('admin.training.training-courses._skill-row', ['index' => $index, 'row' => $row])
            @endforeach
        </div>
        <template data-repeater-template>
            @include('admin.training.training-courses._skill-row', ['index' => '__INDEX__', 'row' => []])
        </template>
        <div>
            <x-button type="button" variant="secondary" size="sm" icon="plus" data-repeater-add hidden data-js-only>{{ __('training.courses.add_skill') }}</x-button>
        </div>
    </div>
</x-form.section>
