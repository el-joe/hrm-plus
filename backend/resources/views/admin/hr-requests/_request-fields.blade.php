{{--
    Fields of a new HR request, shared by the portal "New request" and the admin "on behalf" form.
    Variables: $categories (active, with fields), $selected (?int), $priorities (list<HrRequestPriority>),
    $profileFields (?Collection group => rows from ProfileChangeFields::formFields()).
    The category select drives the rest (select[data-switch="category"] from pages/payroll.js): without
    JavaScript every block is visible and the server keeps only what the category uses.
--}}
@php
    $letterIds = $categories->filter->isLetter()->pluck('id')->implode(' ');
    $profileIds = $categories->filter->isProfileChange()->pluck('id')->implode(' ');
    $describedIds = $categories->reject(fn ($category) => $category->isLetter() || $category->isProfileChange())->pluck('id')->implode(' ');
    $selected = old('hr_request_category_id', $selected);
@endphp

<div class="md:col-span-2">
<x-form.select name="hr_request_category_id" :label="__('hr_requests.portal.choose_category')" required data-switch="category">
    <option value="">—</option>
    @foreach ($categories as $category)
        <option value="{{ $category->id }}" @selected((string) $selected === (string) $category->id)>{{ $category->name }}</option>
    @endforeach
</x-form.select>
</div>

@foreach ($categories as $category)
    @if ($category->description())
        <p class="md:col-span-2 rounded-md bg-surface-muted p-3 text-body-sm text-text-secondary" data-switch-group="category" data-show-for="{{ $category->id }}">
            {{ $category->description() }}
            <span class="block text-caption-md text-text-tertiary">{{ __('hr_requests.portal.category_hint', ['hours' => $category->slaHours()]) }}</span>
        </p>
    @endif
@endforeach

<x-form.input name="subject" :label="__('hr_requests.fields.subject')" :help="__('hr_requests.portal.subject_hint')" maxlength="200" :value="old('subject')" />

<x-form.select name="priority" :label="__('hr_requests.fields.priority')">
    <option value="">{{ __('app.common.system_default') }}</option>
    @foreach ($priorities as $priority)
        <option value="{{ $priority->value }}" @selected(old('priority') === $priority->value)>{{ $priority->label() }}</option>
    @endforeach
</x-form.select>

@if ($letterIds !== '')
    <div data-switch-group="category" data-show-for="{{ $letterIds }}">
        <x-form.select name="letter_language" :label="__('hr_requests.portal.letter_language_hint')">
            @foreach (['ar', 'en'] as $language)
                <option value="{{ $language }}" @selected(old('letter_language', app()->getLocale()) === $language)>{{ __('app.locales.'.$language) }}</option>
            @endforeach
        </x-form.select>
    </div>
@endif

@foreach ($categories as $category)
    @foreach ($category->fields as $field)
        <div data-switch-group="category" data-show-for="{{ $category->id }}" @class(['md:col-span-2' => $field->type->value === 'textarea'])>
            @php($fieldName = 'fields['.$field->key.']')
            @php($fieldId = 'field-'.$category->id.'-'.$field->key)
            @switch($field->type->value)
                @case('textarea')
                    <x-form.textarea :name="$fieldName" :id="$fieldId" :label="$field->label()" :required="$field->is_required" rows="3" :error-keys="['fields.'.$field->key]">{{ old('fields.'.$field->key) }}</x-form.textarea>
                    @break
                @case('select')
                    <x-form.select :name="$fieldName" :id="$fieldId" :label="$field->label()" :required="$field->is_required" :error-keys="['fields.'.$field->key]">
                        <option value="">—</option>
                        @foreach ($field->options ?? [] as $option)
                            <option value="{{ $option }}" @selected(old('fields.'.$field->key) === $option)>{{ $option }}</option>
                        @endforeach
                    </x-form.select>
                    @break
                @default
                    <x-form.input :name="$fieldName" :id="$fieldId" :label="$field->label()" :required="$field->is_required" :error-keys="['fields.'.$field->key]"
                        :type="match ($field->type->value) { 'date' => 'date', 'number' => 'number', default => 'text' }" :value="old('fields.'.$field->key)" />
            @endswitch
        </div>
    @endforeach
@endforeach

<div class="md:col-span-2" @if ($describedIds !== '') data-switch-group="category" data-show-for="{{ $describedIds }} {{ $letterIds }}" @endif>
    <x-form.textarea name="description" :label="__('hr_requests.fields.description')" rows="4" maxlength="5000">{{ old('description') }}</x-form.textarea>
</div>

@if ($profileIds !== '' && $profileFields !== null)
    <div class="md:col-span-2 flex flex-col gap-4" data-switch-group="category" data-show-for="{{ $profileIds }}" data-region="profile-change-fields">
        <div>
            <p class="text-label-lg text-text-primary">{{ __('hr_requests.profile_changes.form_title') }}</p>
            <p class="text-body-sm text-text-secondary">{{ __('hr_requests.profile_changes.form_help') }}</p>
            @error('changes') <p class="mt-1 text-caption-md text-feedback-danger-text" data-field-error>{{ $message }}</p> @enderror
        </div>
        @foreach ($profileFields as $group => $rows)
            <fieldset class="grid gap-4 rounded-lg border border-border-subtle p-4 md:grid-cols-2">
                <legend class="px-1 text-label-md text-text-secondary">{{ __('hr_requests.profile_changes.groups.'.$group) }}</legend>
                @foreach ($rows as $row)
                    @if ($row['field'] === 'marital_status')
                        <x-form.select :name="'changes['.$row['field'].']'" :id="'change-'.$row['field']" :label="$row['label']" :help="__('hr_requests.profile_changes.current_value', ['value' => $row['current']])">
                            <option value="">—</option>
                            @foreach (App\Enums\MaritalStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(old('changes.marital_status') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </x-form.select>
                    @else
                        <x-form.input :name="'changes['.$row['field'].']'" :id="'change-'.$row['field']" :label="$row['label']"
                            :type="$row['field'] === 'passport_expiry_date' ? 'date' : ($row['field'] === 'personal_email' ? 'email' : 'text')"
                            :help="__('hr_requests.profile_changes.current_value', ['value' => $row['current']])" :value="old('changes.'.$row['field'])"
                            :dir="in_array($row['group'], ['sensitive', 'bank'], true) || str_contains($row['field'], 'phone') ? 'ltr' : null" autocomplete="off" />
                    @endif
                @endforeach
            </fieldset>
        @endforeach
    </div>
@endif

<div class="md:col-span-2">
    <x-form.file-input name="attachments[]" :label="__('hr_requests.fields.attachments')" multiple :error-keys="['attachments', 'attachments.*']"
        :help="__('hr_requests.create.files_help', ['count' => 5, 'size' => setting('hr_requests.attachment_max_kb'), 'types' => implode(', ', (array) setting('hr_requests.allowed_extensions'))])" />
</div>
