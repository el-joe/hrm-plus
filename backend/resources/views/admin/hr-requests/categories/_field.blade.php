{{-- One extra-field row of a request category. Variables: $index (int|'__INDEX__'), $field (array), $fieldTypes. --}}
<div class="grid gap-3 rounded-md border border-border-subtle p-3 sm:grid-cols-2 lg:grid-cols-[1fr_1.4fr_1.4fr_1fr_auto] lg:items-end" data-repeater-row>
    <x-form.input :name="'fields['.$index.'][key]'" :id="'field-'.$index.'-key'" :label="__('hr_requests.categories.field_key')" dir="ltr" maxlength="40"
        :value="$field['key'] ?? null" :error-keys="['fields.'.$index.'.key']" />
    <x-form.input :name="'fields['.$index.'][label_ar]'" :id="'field-'.$index.'-label-ar'" :label="__('hr_requests.categories.field_label_ar')" dir="rtl" maxlength="150"
        :value="$field['label_ar'] ?? null" :error-keys="['fields.'.$index.'.label_ar']" />
    <x-form.input :name="'fields['.$index.'][label_en]'" :id="'field-'.$index.'-label-en'" :label="__('hr_requests.categories.field_label_en')" dir="ltr" maxlength="150"
        :value="$field['label_en'] ?? null" :error-keys="['fields.'.$index.'.label_en']" />
    <x-form.select :name="'fields['.$index.'][type]'" :id="'field-'.$index.'-type'" :label="__('hr_requests.categories.field_type')" :error-keys="['fields.'.$index.'.type']">
        @foreach ($fieldTypes as $type)
            <option value="{{ $type->value }}" @selected(($field['type'] ?? 'text') === $type->value)>{{ $type->label() }}</option>
        @endforeach
    </x-form.select>
    <div class="flex justify-end">
        <x-icon-button type="button" icon="trash" size="sm" variant="danger" :label="__('hr_requests.categories.remove_field')" data-repeater-remove hidden data-js-only />
    </div>
    <div class="sm:col-span-2 lg:col-span-4">
        <x-form.textarea :name="'fields['.$index.'][options]'" :id="'field-'.$index.'-options'" :label="__('hr_requests.categories.field_options')" rows="2"
            :help="__('hr_requests.categories.options_hint')" :error-keys="['fields.'.$index.'.options']">{{ $field['options'] ?? '' }}</x-form.textarea>
    </div>
    <div class="flex items-center">
        <input type="hidden" name="fields[{{ $index }}][is_required]" value="0">
        <x-form.checkbox :name="'fields['.$index.'][is_required]'" :id="'field-'.$index.'-required'" value="1" :label="__('hr_requests.categories.field_is_required')" :checked="(bool) ($field['is_required'] ?? false)" />
    </div>
</div>
