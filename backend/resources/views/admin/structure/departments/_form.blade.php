<x-form.section :title="__('structure.common.identity')" :description="__('structure.common.identity_help')">
    @include('admin.structure._identity-fields')
</x-form.section>

<x-form.section :title="__('structure.departments.hierarchy')" :description="__('structure.departments.hierarchy_help')">
    <x-form.select name="parent_id" :label="__('structure.fields.parent_id')">
        <option value="">{{ __('structure.departments.top_level') }}</option>
        @foreach ($parents as $parent)
            <option value="{{ $parent->id }}" @selected((string) old('parent_id', $record->parent_id) === (string) $parent->id)>{{ $parent->code }} · {{ $parent->name }}</option>
        @endforeach
    </x-form.select>

    @php($branchRequired = auth()->user()->hasDataRestrictions())
    <x-form.select name="branch_id" :label="__('structure.fields.branch_id')" :required="$branchRequired"
        :help="$branchRequired ? __('structure.departments.branch_required_help') : __('structure.departments.branch_help')">
        <option value="">{{ __('structure.departments.company_wide') }}</option>
        @foreach ($branches as $branch)
            <option value="{{ $branch->id }}" @selected((string) old('branch_id', $record->branch_id) === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
        @endforeach
    </x-form.select>

    <x-form.select name="cost_center_id" :label="__('structure.fields.cost_center_id')">
        <option value="">{{ __('structure.common.not_set') }}</option>
        @foreach ($costCenters as $costCenter)
            <option value="{{ $costCenter->id }}" @selected((string) old('cost_center_id', $record->cost_center_id) === (string) $costCenter->id)>{{ $costCenter->code }} · {{ $costCenter->name }}</option>
        @endforeach
    </x-form.select>

    @if ($record->exists)
        <x-form.select name="head_position_id" :label="__('structure.fields.head_position_id')" :help="__('structure.departments.head_help')">
            <option value="">{{ __('structure.common.not_set') }}</option>
            @foreach ($headPositions as $position)
                <option value="{{ $position->id }}" @selected((string) old('head_position_id', $record->head_position_id) === (string) $position->id)>{{ $position->code }} · {{ $position->name }}</option>
            @endforeach
        </x-form.select>
    @endif

    @include('admin.structure._description-field')
    @include('admin.structure._active-switch')
</x-form.section>
