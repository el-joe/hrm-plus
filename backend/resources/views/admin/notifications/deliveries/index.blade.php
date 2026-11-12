@extends('layouts.admin', ['subtitle' => __('notifications.deliveries.subtitle')])

@section('title', __('notifications.deliveries.title'))

@section('content')
    @if ($pushSimulated)
        <x-alert tone="warning" data-notice="push-simulated">
            <span class="inline-flex flex-wrap items-center gap-2">@include('admin.attendance._simulated') {{ __('notifications.deliveries.simulated_notice') }}</span>
        </x-alert>
    @endif
    @if ($mailLogged)
        <x-alert tone="info" data-notice="mail-log">{{ __('notifications.deliveries.mail_log_notice') }}</x-alert>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card :label="__('notifications.deliveries.counts.sent')" :value="$counts['sent']" icon="check-circle" />
        <x-stat-card :label="__('notifications.deliveries.counts.failed')" :value="$counts['failed']" icon="x-circle" />
        <x-stat-card :label="__('notifications.deliveries.counts.simulated')" :value="$counts['simulated']" icon="smartphone" />
        <x-stat-card :label="__('notifications.deliveries.counts.devices')" :value="$counts['devices']" icon="smartphone" />
    </div>

    <x-filter-bar :action="route('admin.notification-deliveries.index')" :reset="route('admin.notification-deliveries.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="__('notifications.deliveries.recipient')"
            :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="channel" :label="__('notifications.deliveries.channel')" hide-label class="md:w-40">
            <option value="">{{ __('notifications.deliveries.channel') }}: {{ __('app.common.all') }}</option>
            @foreach (['mail', 'push'] as $channel)
                <option value="{{ $channel }}" @selected(($filters['channel'] ?? '') === $channel)>{{ __('notifications.channels.'.$channel) }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" :label="__('notifications.deliveries.status')" hide-label class="md:w-40">
            <option value="">{{ __('notifications.deliveries.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="event" :label="__('notifications.deliveries.event')" hide-label class="md:w-64">
            <option value="">{{ __('notifications.deliveries.event') }}: {{ __('app.common.all') }}</option>
            @foreach ($events as $key => $label)
                <option value="{{ $key }}" @selected(($filters['event'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </x-form.select>
        <x-form.input name="from" type="date" :label="__('app.common.date_from')" hide-label :value="$filters['from'] ?? ''" class="md:w-40" />
        <x-form.input name="to" type="date" :label="__('app.common.date_to')" hide-label :value="$filters['to'] ?? ''" class="md:w-40" />
    </x-filter-bar>

    <x-table :caption="__('notifications.deliveries.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('notifications.deliveries.created') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('notifications.deliveries.event') }}</x-table.th>
                <x-table.th>{{ __('notifications.deliveries.user') }}</x-table.th>
                <x-table.th>{{ __('notifications.deliveries.channel') }}</x-table.th>
                <x-table.th>{{ __('notifications.deliveries.status') }}</x-table.th>
                <x-table.th>{{ __('notifications.deliveries.attempts') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('notifications.deliveries.error') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($deliveries as $delivery)
            <x-table.row>
                <x-table.td :label="__('notifications.deliveries.created')"><span dir="ltr" class="tabular-nums">{{ $delivery->created_at->format('Y-m-d H:i') }}</span></x-table.td>
                <x-table.td :label="__('notifications.deliveries.event')">
                    <span class="block text-label-md">{{ __('notifications.events.'.$delivery->event) }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $delivery->event }}</span>
                </x-table.td>
                <x-table.td :label="__('notifications.deliveries.user')">
                    <span class="block">{{ $delivery->user?->name ?? '—' }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $delivery->recipient }}</span>
                </x-table.td>
                <x-table.td :label="__('notifications.deliveries.channel')">
                    {{ __('notifications.channels.'.$delivery->channel) }}
                    @if ($delivery->provider)<span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $delivery->provider }}</span>@endif
                </x-table.td>
                <x-table.td :label="__('notifications.deliveries.status')">
                    @if ($delivery->status === App\Enums\NotificationDeliveryStatus::Simulated)
                        @include('admin.attendance._simulated')
                    @else
                        <x-badge :tone="$delivery->status->tone()" dot>{{ $delivery->status->label() }}</x-badge>
                    @endif
                </x-table.td>
                <x-table.td :label="__('notifications.deliveries.attempts')" class="tabular-nums">{{ $delivery->attempts }}</x-table.td>
                <x-table.td :label="__('notifications.deliveries.error')"><span class="break-words text-caption-md text-feedback-danger-text">{{ $delivery->error }}</span></x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="send" :title="__('notifications.deliveries.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$deliveries" />
        </x-slot:footer>
    </x-table>
@endsection
