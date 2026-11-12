@extends('layouts.admin', ['subtitle' => __('assets.categories.subtitle')])

@section('title', __('assets.categories.title'))

@section('page-actions')
    @can('create', App\Models\AssetCategory::class)
        <x-button :href="route('admin.asset-categories.create')" icon="plus">{{ __('assets.categories.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.asset-categories.index')" :reset="route('admin.asset-categories.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-64" data-filter-debounce />
        <x-form.select name="status" :label="__('assets.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('assets.fields.status') }}: {{ __('assets.all') }}</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('assets.categories.active') }}</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('assets.categories.inactive') }}</option>
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('assets.categories.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="code">{{ __('assets.categories.fields.code') }}</x-table.th>
                <x-table.th sort="name" class="min-w-56">{{ __('assets.categories.fields.name') }}</x-table.th>
                <x-table.th>{{ __('assets.categories.fields.requires_serial') }}</x-table.th>
                <x-table.th sort="assets">{{ __('assets.categories.fields.assets') }}</x-table.th>
                <x-table.th>{{ __('assets.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($categories as $category)
            <x-table.row>
                <x-table.td :label="__('assets.categories.fields.code')"><span dir="ltr" class="text-label-md">{{ $category->code }}</span></x-table.td>
                <x-table.td :label="__('assets.categories.fields.name')">
                    <span class="text-label-md text-text-primary">{{ $category->name }}</span>
                    @if ($category->description)
                        <span class="block text-caption-md text-text-tertiary">{{ $category->description }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('assets.categories.fields.requires_serial')">{{ $category->requires_serial ? __('app.common.yes') : __('app.common.no') }}</x-table.td>
                <x-table.td :label="__('assets.categories.fields.assets')" class="tabular-nums">
                    <a class="text-text-link hover:underline" href="{{ route('admin.assets.index', ['category' => $category->id]) }}">{{ $category->assets_count }}</a>
                </x-table.td>
                <x-table.td :label="__('assets.fields.status')">
                    <x-badge :tone="$category->is_active ? 'success' : 'neutral'" dot>{{ $category->is_active ? __('assets.categories.active') : __('assets.categories.inactive') }}</x-badge>
                </x-table.td>
                <x-table.td>
                    <div class="flex items-center justify-end gap-1">
                        @can('update', $category)
                            <x-icon-button icon="pencil" size="sm" :href="route('admin.asset-categories.edit', $category)" :label="__('app.actions.edit')" />
                        @endcan
                        @if ($category->assets_count === 0)
                            @can('delete', $category)
                                <form method="POST" action="{{ route('admin.asset-categories.destroy', $category) }}" data-confirm="{{ __('assets.categories.confirm_delete') }}" data-confirm-tone="danger">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('app.actions.delete')" />
                                </form>
                            @endcan
                        @endif
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="layers" :title="__('assets.categories.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$categories" />
        </x-slot:footer>
    </x-table>
@endsection
