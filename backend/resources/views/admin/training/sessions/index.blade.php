@extends('layouts.admin', ['subtitle' => __('training.sessions.subtitle')])

@section('title', __('training.sessions.title'))

@section('page-actions')
    @can('create', App\Models\TrainingSession::class)
        <x-button :href="route('admin.training-sessions.create')" icon="plus">{{ __('training.sessions.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <div class="grid gap-3 sm:grid-cols-3">
        <x-stat-card :label="__('training.sessions.upcoming')" :value="$counts['upcoming']" icon="calendar" :href="route('admin.training-sessions.index', ['when' => 'upcoming', 'status' => 'scheduled'])" />
        <x-stat-card :label="__('training.sessions.this_month')" :value="$counts['month']" icon="clock" />
        <x-stat-card :label="__('training.enrolments.awaiting')" :value="$counts['pending']" icon="inbox" :href="route('admin.training-enrolments.index', ['status' => 'pending'])" />
    </div>

    <x-filter-bar :action="route('admin.training-sessions.index')" :reset="route('admin.training-sessions.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="when" :label="__('training.sessions.when')" hide-label class="md:w-40">
            <option value="">{{ __('training.sessions.when') }}: {{ __('app.common.all') }}</option>
            @foreach (['upcoming', 'past'] as $when)
                <option value="{{ $when }}" @selected(($filters['when'] ?? '') === $when)>{{ __('training.sessions.'.$when) }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" :label="__('training.fields.status')" hide-label class="md:w-40">
            <option value="">{{ __('training.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="training_course_id" :label="__('training.fields.course')" hide-label class="md:w-56">
            <option value="">{{ __('training.fields.course') }}: {{ __('app.common.all') }}</option>
            @foreach ($courses as $course)
                <option value="{{ $course->id }}" @selected((string) ($filters['training_course_id'] ?? '') === (string) $course->id)>{{ $course->name }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('training.sessions.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="reference">{{ __('training.fields.reference') }}</x-table.th>
                <x-table.th class="min-w-56">{{ __('training.fields.course') }}</x-table.th>
                <x-table.th sort="start_date">{{ __('training.fields.dates') }}</x-table.th>
                <x-table.th>{{ __('training.fields.place') }}</x-table.th>
                <x-table.th sort="capacity">{{ __('training.fields.seats') }}</x-table.th>
                <x-table.th>{{ __('training.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($sessions as $session)
            <x-table.row :data-href="route('admin.training-sessions.show', $session)">
                <x-table.td :label="__('training.fields.reference')" class="whitespace-nowrap">
                    <a href="{{ route('admin.training-sessions.show', $session) }}" class="text-label-md text-text-link hover:underline" dir="ltr">{{ $session->reference }}</a>
                </x-table.td>
                <x-table.td :label="__('training.fields.course')">
                    <span class="text-label-md text-text-primary">{{ $session->course->name }}</span>
                    <span class="block text-caption-md text-text-tertiary">{{ $session->course->provider?->name }}</span>
                </x-table.td>
                <x-table.td :label="__('training.fields.dates')">@include('admin.training._session-when')</x-table.td>
                <x-table.td :label="__('training.fields.place')" class="text-body-sm text-text-secondary">
                    {{ $session->delivery_mode->label() }}
                    @if ($session->location)<span class="block text-caption-md text-text-tertiary">{{ $session->location }}</span>@endif
                </x-table.td>
                <x-table.td :label="__('training.fields.seats')" class="tabular-nums">
                    {{ __('training.sessions.seats_of', ['taken' => $session->seats_taken, 'capacity' => $session->capacity]) }}
                    @if ($session->pending_count > 0)
                        <span class="block"><x-badge tone="warning">{{ trans_choice('training.sessions.pending_requests', $session->pending_count, ['count' => $session->pending_count]) }}</x-badge></span>
                    @elseif ($session->seats_taken >= $session->capacity && $session->isScheduled())
                        <span class="block"><x-badge tone="danger">{{ __('training.sessions.full') }}</x-badge></span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('training.fields.status')"><x-badge :tone="$session->status->tone()" dot>{{ $session->status->label() }}</x-badge></x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="calendar" :title="__('training.sessions.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$sessions" />
        </x-slot:footer>
    </x-table>
@endsection
