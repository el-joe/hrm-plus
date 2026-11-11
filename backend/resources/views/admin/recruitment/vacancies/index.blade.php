@extends('layouts.admin', ['subtitle' => __('recruitment.vacancies.subtitle')])

@section('title', __('recruitment.vacancies.title'))

@section('page-actions')
    @can('create', App\Models\Vacancy::class)
        <x-button :href="route('admin.vacancies.create')" icon="plus">{{ __('recruitment.vacancies.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.vacancies.index')" :reset="route('admin.vacancies.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label
            :placeholder="__('recruitment.vacancies.search_placeholder')" :value="$filters['search'] ?? ''" class="md:w-72" data-filter-debounce />
        <x-form.select name="status" id="filter-status" :label="__('recruitment.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('recruitment.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($options['statuses'] as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        @include('admin.structure._id-filter', ['name' => 'branch_id', 'label' => __('recruitment.fields.branch_id'), 'options' => $options['branches']])
        @include('admin.structure._id-filter', ['name' => 'department_id', 'label' => __('recruitment.fields.department_id'), 'options' => $options['departments']])
    </x-filter-bar>

    <x-table :caption="__('recruitment.vacancies.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="title" class="min-w-64">{{ __('recruitment.fields.title') }}</x-table.th>
                <x-table.th>{{ __('recruitment.fields.department_id') }}</x-table.th>
                <x-table.th>{{ __('recruitment.fields.openings') }}</x-table.th>
                <x-table.th>{{ __('recruitment.vacancies.applicants') }}</x-table.th>
                <x-table.th sort="deadline">{{ __('recruitment.fields.application_deadline') }}</x-table.th>
                <x-table.th sort="status">{{ __('recruitment.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($vacancies as $vacancy)
            <x-table.row :data-href="route('admin.vacancies.show', $vacancy)">
                <x-table.td :label="__('recruitment.fields.title')">
                    <a href="{{ route('admin.vacancies.show', $vacancy) }}" class="block text-label-md text-text-primary hover:text-text-link">{{ $vacancy->title }}</a>
                    <span class="text-caption-md text-text-tertiary"><span dir="ltr">{{ $vacancy->reference }}</span>@if ($vacancy->is_public) · {{ __('recruitment.vacancies.public') }}@endif</span>
                </x-table.td>
                <x-table.td :label="__('recruitment.fields.department_id')">
                    <span class="block">{{ $vacancy->department?->name }}</span>
                    <span class="block text-caption-md text-text-tertiary">{{ $vacancy->branch?->name }}@if ($vacancy->employmentType) · {{ $vacancy->employmentType->name }}@endif</span>
                </x-table.td>
                <x-table.td :label="__('recruitment.fields.openings')" class="tabular-nums">{{ $vacancy->filled_count }} / {{ $vacancy->openings }}</x-table.td>
                <x-table.td :label="__('recruitment.vacancies.applicants')" class="tabular-nums">
                    {{ $vacancy->active_applications_count }}
                    <span class="text-caption-md text-text-tertiary">/ {{ $vacancy->applications_count }}</span>
                </x-table.td>
                <x-table.td :label="__('recruitment.fields.application_deadline')" class="whitespace-nowrap tabular-nums text-text-secondary"><span dir="ltr">{{ $vacancy->application_deadline?->toDateString() ?? '—' }}</span></x-table.td>
                <x-table.td :label="__('recruitment.fields.status')">@include('admin.recruitment._badge', ['value' => $vacancy->status])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="briefcase" :title="__('recruitment.vacancies.empty')" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$vacancies" />
        </x-slot:footer>
    </x-table>
@endsection
