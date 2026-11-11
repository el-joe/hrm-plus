{{--
    Search + status filter controls (inside x-filter-bar). Variables: $filters.
--}}
<x-form.input name="search" id="search" type="search" icon="search" :label="__('app.common.search')" hide-label
    :placeholder="__('structure.common.search_placeholder')" :value="$filters['search'] ?? ''" class="md:w-64" data-filter-debounce />

<x-form.select name="status" id="status" :label="__('structure.common.status')" hide-label class="md:w-40">
    <option value="">{{ __('structure.common.status') }}: {{ __('app.common.all') }}</option>
    @foreach (['active', 'inactive'] as $status)
        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('structure.common.'.$status) }}</option>
    @endforeach
</x-form.select>
