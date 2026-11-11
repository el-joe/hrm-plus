{{-- Full-width description textarea. Variables: $record. --}}
<div class="md:col-span-2">
    <x-form.textarea name="description" :label="__('structure.fields.description')" maxlength="1000" rows="3">{{ old('description', $record->description) }}</x-form.textarea>
</div>
