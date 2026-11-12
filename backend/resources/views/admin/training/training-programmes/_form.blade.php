<x-form.section :title="__('training.programmes.identity')">
    @include('admin.structure._identity-fields')
    <div class="md:col-span-2">
        <x-form.textarea name="description" :label="__('training.fields.description')" maxlength="2000" rows="3">{{ old('description', $record->description) }}</x-form.textarea>
    </div>
    @include('admin.structure._active-switch')
</x-form.section>
