<x-form.section :title="__('structure.common.identity')" :description="__('structure.common.identity_help')">
    @include('admin.structure._identity-fields')
</x-form.section>

<x-form.section :title="__('structure.common.details')">
    <x-form.select name="parent_id" :label="__('structure.cost_centers.parent')" :help="__('structure.cost_centers.parent_help')">
        <option value="">{{ __('structure.common.none') }}</option>
        @foreach ($parents as $parent)
            <option value="{{ $parent->id }}" @selected((string) old('parent_id', $record->parent_id) === (string) $parent->id)>{{ $parent->code }} · {{ $parent->name }}</option>
        @endforeach
    </x-form.select>
    <div class="hidden md:block" aria-hidden="true"></div>
    @include('admin.structure._description-field')
    @include('admin.structure._active-switch')
</x-form.section>
