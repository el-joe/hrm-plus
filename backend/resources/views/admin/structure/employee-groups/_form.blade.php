<x-form.section :title="__('structure.common.identity')" :description="__('structure.common.identity_help')">
    @include('admin.structure._identity-fields')
</x-form.section>

<x-form.section :title="__('structure.common.details')">
    @include('admin.structure._description-field')
    @include('admin.structure._active-switch')
</x-form.section>
