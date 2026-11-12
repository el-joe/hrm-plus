@php use App\Services\Attendance\AttendanceClock; use App\Services\Attendance\Mobile\AttemptReason; @endphp
@extends('layouts.admin', ['subtitle' => __('mobile.review.subtitle')])

@section('title', __('mobile.review.title'))

@section('content')
    <x-tabs :items="collect(['queue', 'flagged', 'accepted', 'rejected', 'all'])->map(fn ($view) => [
        'label' => __('mobile.review.views.'.$view).($view === 'queue' ? ' ('.$queueCount.')' : ''),
        'url' => route('admin.attendance.reviews.index', [...request()->except(['view', 'page']), 'view' => $view]),
        'active' => $filters['view'] === $view,
    ])->all()" />

    <x-filter-bar :action="route('admin.attendance.reviews.index')" :reset="route('admin.attendance.reviews.index', ['view' => $filters['view']])">
        <input type="hidden" name="view" value="{{ $filters['view'] }}">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="__('attendance.common.search_employee')" :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="reason" :label="__('mobile.fields.reason')" hide-label class="md:w-52">
            <option value="">{{ __('mobile.fields.reason') }}: {{ __('app.common.all') }}</option>
            @foreach ($reasons as $reason)
                <option value="{{ $reason }}" @selected(($filters['reason'] ?? '') === $reason)>{{ AttemptReason::label($reason) }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="channel" :label="__('mobile.fields.channel')" hide-label class="md:w-40">
            <option value="">{{ __('mobile.fields.channel') }}: {{ __('app.common.all') }}</option>
            @foreach ($channels as $channel)
                <option value="{{ $channel->value }}" @selected(($filters['channel'] ?? '') === $channel->value)>{{ $channel->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="branch_id" :label="__('attendance.fields.branch')" hide-label class="md:w-44">
            <option value="">{{ __('attendance.fields.branch') }}: {{ __('app.common.all') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.input name="from" type="date" :label="__('attendance.fields.from')" hide-label :value="$filters['from'] ?? ''" class="md:w-40" />
        <x-form.input name="to" type="date" :label="__('attendance.fields.to')" hide-label :value="$filters['to'] ?? ''" class="md:w-40" />
    </x-filter-bar>

    <x-table :caption="__('mobile.review.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('attendance.fields.employee') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.time') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.direction') }}</x-table.th>
                <x-table.th>{{ __('mobile.fields.channel') }}</x-table.th>
                <x-table.th>{{ __('mobile.fields.location') }}</x-table.th>
                <x-table.th>{{ __('mobile.fields.face') }}</x-table.th>
                <x-table.th>{{ __('mobile.fields.reasons') }}</x-table.th>
                <x-table.th>{{ __('mobile.fields.outcome') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($attempts as $attempt)
            <x-table.row data-href="{{ route('admin.attendance.reviews.show', $attempt) }}">
                <x-table.td :label="__('attendance.fields.employee')">
                    <a href="{{ route('admin.attendance.reviews.show', $attempt) }}" class="block text-label-md text-text-link hover:underline">{{ $attempt->employee?->name }}</a>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $attempt->employee?->employee_number }}</span>
                </x-table.td>
                <x-table.td :label="__('attendance.fields.time')" class="tabular-nums"><span dir="ltr">{{ $attempt->server_time->setTimezone($attempt->timezone)->format('Y-m-d H:i') }}</span></x-table.td>
                <x-table.td :label="__('attendance.fields.direction')">{{ $attempt->direction->label() }}</x-table.td>
                <x-table.td :label="__('mobile.fields.channel')"><x-badge :tone="$attempt->channel === \App\Enums\CheckInChannel::Web ? 'info' : 'neutral'" data-channel="{{ $attempt->channel->value }}">{{ $attempt->channel->label() }}</x-badge></x-table.td>
                <x-table.td :label="__('mobile.fields.location')">
                    @if ($attempt->allow_anywhere)
                        <x-badge tone="info">{{ __('mobile.locations.anywhere') }}</x-badge>
                    @elseif ($attempt->distance_meters !== null)
                        <span class="text-label-md">{{ $attempt->location?->name }}</span>
                        <span class="block text-caption-md text-text-tertiary">{{ __('mobile.review.distance', ['distance' => $attempt->distance_meters, 'radius' => $attempt->allowed_radius_meters, 'accuracy' => (int) $attempt->accuracy_meters]) }}</span>
                    @else
                        —
                    @endif
                </x-table.td>
                <x-table.td :label="__('mobile.fields.face')">
                    @if ($attempt->faceVerification)
                        <x-badge :tone="$attempt->faceVerification->result->tone()">{{ $attempt->faceVerification->result->label() }}</x-badge>
                        @if ($attempt->faceVerification->is_simulated) @include('admin.attendance._simulated') @endif
                    @else
                        <span class="text-caption-md text-text-tertiary">{{ $attempt->face_mode->label() }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('mobile.fields.reasons')">
                    @foreach ($attempt->reasonCodes() as $code)
                        <span class="block text-caption-md">{{ AttemptReason::label($code) }}</span>
                    @endforeach
                </x-table.td>
                <x-table.td :label="__('mobile.fields.outcome')">@include('admin.attendance.reviews._outcome')</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="8" icon="map-pin" :title="__('mobile.review.empty')" :description="$filters['view'] === 'queue' ? __('mobile.review.empty_queue') : null" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$attempts" />
        </x-slot:footer>
    </x-table>
@endsection
