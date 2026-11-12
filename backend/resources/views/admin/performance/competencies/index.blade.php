@extends('layouts.admin', ['subtitle' => __('performance.competencies.subtitle')])

@section('title', __('performance.competencies.title'))

@section('page-actions')
    @can('create', App\Models\Competency::class)
        <x-button :href="route('admin.competencies.create')" icon="plus">{{ __('performance.competencies.create') }}</x-button>
    @endcan
@endsection

@section('content')
    @if ($errors->has('record'))
        <x-alert tone="danger">{{ $errors->first('record') }}</x-alert>
    @endif
    <x-filter-bar :action="route('admin.competencies.index')" :reset="route('admin.competencies.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="category" :label="__('performance.fields.category')" hide-label class="md:w-48">
            <option value="">{{ __('performance.fields.category') }}: {{ __('app.common.all') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->value }}" @selected(($filters['category'] ?? '') === $category->value)>{{ $category->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" :label="__('performance.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('performance.fields.status') }}: {{ __('app.common.all') }}</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('structure.common.active') }}</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('structure.common.inactive') }}</option>
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('performance.competencies.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-56">{{ __('performance.fields.name') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.category') }}</x-table.th>
                <x-table.th class="min-w-56">{{ __('performance.fields.description') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('performance.fields.name')">
                    <span class="text-label-md text-text-primary">{{ $record->name }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $record->code }}</span>
                </x-table.td>
                <x-table.td :label="__('performance.fields.category')">{{ $record->category->label() }}</x-table.td>
                <x-table.td :label="__('performance.fields.description')" class="text-body-sm text-text-secondary">{{ $record->description ?? '—' }}</x-table.td>
                <x-table.td :label="__('performance.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'competencies'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="layers" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
