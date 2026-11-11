@extends('layouts.admin', ['subtitle' => __('recruitment.candidates.subtitle')])

@section('title', __('recruitment.candidates.title'))

@section('page-actions')
    @can('create', App\Models\Candidate::class)
        <x-button :href="route('admin.candidates.create')" icon="user-plus">{{ __('recruitment.candidates.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.candidates.index')" :reset="route('admin.candidates.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label
            :placeholder="__('recruitment.candidates.search_placeholder')" :value="$filters['search'] ?? ''" class="md:w-72" data-filter-debounce />
        <x-form.select name="vacancy_id" id="filter-vacancy" :label="__('recruitment.fields.vacancy')" hide-label class="md:w-56">
            <option value="">{{ __('recruitment.fields.vacancy') }}: {{ __('app.common.all') }}</option>
            @foreach ($vacancies as $vacancy)
                <option value="{{ $vacancy->id }}" @selected((string) ($filters['vacancy_id'] ?? '') === (string) $vacancy->id)>{{ $vacancy->reference }} · {{ $vacancy->title }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="source" id="filter-source" :label="__('recruitment.fields.source')" hide-label class="md:w-44">
            <option value="">{{ __('recruitment.fields.source') }}: {{ __('app.common.all') }}</option>
            @foreach ($sources as $source)
                <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ $source->label() }}</option>
            @endforeach
        </x-form.select>
        @if ($tags->isNotEmpty())
            <x-form.select name="tag" id="filter-tag" :label="__('recruitment.fields.tags')" hide-label class="md:w-40">
                <option value="">{{ __('recruitment.fields.tags') }}: {{ __('app.common.all') }}</option>
                @foreach ($tags as $tag)
                    <option value="{{ $tag }}" @selected(($filters['tag'] ?? '') === $tag)>{{ $tag }}</option>
                @endforeach
            </x-form.select>
        @endif
    </x-filter-bar>

    <x-table :caption="__('recruitment.candidates.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="name" class="min-w-64">{{ __('recruitment.fields.name') }}</x-table.th>
                <x-table.th>{{ __('recruitment.candidates.applications') }}</x-table.th>
                <x-table.th sort="experience">{{ __('recruitment.fields.experience_years') }}</x-table.th>
                <x-table.th>{{ __('recruitment.fields.source') }}</x-table.th>
                <x-table.th sort="created">{{ __('recruitment.candidates.added') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($candidates as $candidate)
            <x-table.row :data-href="route('admin.candidates.show', $candidate)">
                <x-table.td :label="__('recruitment.fields.name')">
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$candidate->name" size="sm" />
                        <div class="min-w-0">
                            <a href="{{ route('admin.candidates.show', $candidate) }}" class="block truncate text-label-md text-text-primary hover:text-text-link">{{ $candidate->name }}</a>
                            <p class="truncate text-caption-md text-text-tertiary">{{ $candidate->current_job_title ?? '—' }}@if ($candidate->email) · <span dir="ltr">{{ $candidate->email }}</span>@endif</p>
                        </div>
                        @if ($candidate->isHired())
                            <x-badge tone="success">{{ __('recruitment.candidates.hired') }}</x-badge>
                        @endif
                    </div>
                </x-table.td>
                <x-table.td :label="__('recruitment.candidates.applications')">
                    @forelse ($candidate->applications->take(2) as $application)
                        <span class="block text-body-sm">{{ $application->vacancy->title }} — <span class="text-text-secondary">{{ $application->stage->name }}</span></span>
                    @empty
                        <span class="text-text-tertiary">{{ __('recruitment.candidates.talent_pool') }}</span>
                    @endforelse
                    @if ($candidate->applications->count() > 2)
                        <span class="text-caption-md text-text-tertiary">+{{ $candidate->applications->count() - 2 }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('recruitment.fields.experience_years')" class="tabular-nums">{{ $candidate->experience_years ?? '—' }}</x-table.td>
                <x-table.td :label="__('recruitment.fields.source')" class="text-text-secondary">{{ $candidate->source->label() }}</x-table.td>
                <x-table.td :label="__('recruitment.candidates.added')" class="whitespace-nowrap tabular-nums text-text-secondary"><span dir="ltr">{{ $candidate->created_at->toDateString() }}</span></x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="users" :title="__('recruitment.candidates.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$candidates" />
        </x-slot:footer>
    </x-table>
@endsection
