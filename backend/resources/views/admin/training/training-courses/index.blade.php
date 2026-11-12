@extends('layouts.admin', ['subtitle' => __('training.courses.subtitle')])

@section('title', __('training.courses.title'))

@section('page-actions')
    @can('create', App\Models\TrainingCourse::class)
        <x-button :href="route('admin.training-courses.create')" icon="plus">{{ __('training.courses.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.training-courses.index')" :reset="route('admin.training-courses.index')">
        @include('admin.structure._filters')
        <x-form.select name="training_programme_id" :label="__('training.fields.programme')" hide-label class="md:w-48">
            <option value="">{{ __('training.fields.programme') }}: {{ __('app.common.all') }}</option>
            @foreach ($programmes as $programme)
                <option value="{{ $programme->id }}" @selected((string) ($filters['training_programme_id'] ?? '') === (string) $programme->id)>{{ $programme->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="training_provider_id" :label="__('training.fields.provider')" hide-label class="md:w-48">
            <option value="">{{ __('training.fields.provider') }}: {{ __('app.common.all') }}</option>
            @foreach ($providers as $provider)
                <option value="{{ $provider->id }}" @selected((string) ($filters['training_provider_id'] ?? '') === (string) $provider->id)>{{ $provider->name }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('training.courses.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-56">{{ __('training.fields.name') }}</x-table.th>
                <x-table.th>{{ __('training.fields.delivery_mode') }}</x-table.th>
                <x-table.th>{{ __('training.fields.duration_hours') }}</x-table.th>
                <x-table.th>{{ __('training.fields.cost_per_participant') }}</x-table.th>
                <x-table.th>{{ __('training.courses.skills') }}</x-table.th>
                <x-table.th>{{ __('training.sessions.title') }}</x-table.th>
                <x-table.th>{{ __('training.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('training.fields.name')">
                    @include('admin.structure._name-cell', ['sub' => $record->provider?->name])
                    @if ($record->programme)
                        <span class="block text-caption-md text-text-tertiary">{{ $record->programme->name }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('training.fields.delivery_mode')">{{ $record->delivery_mode->label() }}</x-table.td>
                <x-table.td :label="__('training.fields.duration_hours')" class="tabular-nums"><span dir="ltr">{{ $record->duration_hours }}</span></x-table.td>
                <x-table.td :label="__('training.fields.cost_per_participant')">
                    @include('admin.payroll._money', ['amount' => $record->cost_per_participant])
                    @if ($record->issues_certificate)
                        <span class="block"><x-badge tone="info">{{ $record->certificate_validity_months ? __('training.courses.certificate_months', ['months' => $record->certificate_validity_months]) : __('training.courses.certificate_no_expiry') }}</x-badge></span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('training.courses.skills')" class="text-caption-md text-text-secondary">
                    @forelse ($record->courseSkills as $row)
                        <span class="block">{{ $row->skill->name }} · {{ $row->level }}/5</span>
                    @empty
                        —
                    @endforelse
                </x-table.td>
                <x-table.td :label="__('training.sessions.title')" class="tabular-nums">
                    <a class="text-text-link hover:underline" href="{{ route('admin.training-sessions.index', ['training_course_id' => $record->id]) }}">{{ $record->sessions_count }}</a>
                    @can('create', App\Models\TrainingSession::class)
                        @if ($record->is_active)
                            <a class="block text-caption-md text-text-link hover:underline" href="{{ route('admin.training-sessions.create', ['course' => $record->id]) }}">{{ __('training.sessions.schedule') }}</a>
                        @endif
                    @endcan
                </x-table.td>
                <x-table.td :label="__('training.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'training-courses'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="8" icon="book-open" :title="__('training.courses.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
