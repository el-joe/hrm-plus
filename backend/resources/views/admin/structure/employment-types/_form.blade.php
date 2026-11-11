<x-form.section :title="__('structure.common.identity')" :description="__('structure.common.identity_help')">
    @include('admin.structure._identity-fields')
</x-form.section>

<x-form.section :title="__('structure.common.details')">
    <div class="md:col-span-2">
        <input type="hidden" name="is_fixed_term" value="0">
        <x-form.switch name="is_fixed_term" :label="__('structure.employment_types.fixed_term')" :description="__('structure.employment_types.fixed_term_help')"
            :checked="(bool) old('is_fixed_term', $record->is_fixed_term)" />
    </div>
    @include('admin.structure._active-switch')
</x-form.section>
