@extends('layouts.admin', ['subtitle' => __('devices.index.subtitle')])

@section('title', __('devices.index.title'))

@section('page-actions')
    @can('attendance.view')
        <x-button variant="secondary" icon="inbox" :href="route('admin.attendance.events.index', ['state' => 'unmatched'])">
            {{ __('devices.index.unmatched', ['count' => $unmatchedCount]) }}
        </x-button>
    @endcan
    @can('create', App\Models\AttendanceDevice::class)
        <x-button icon="plus" :href="route('admin.attendance-devices.create')">{{ __('devices.index.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-alert tone="info" :title="__('devices.index.no_vendor_title')">{{ __('devices.index.no_vendor_help') }}</x-alert>

    <x-filter-bar :action="route('admin.attendance-devices.index')" :reset="route('admin.attendance-devices.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="__('devices.index.search')" :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="branch_id" :label="__('attendance.fields.branch')" hide-label class="md:w-48">
            <option value="">{{ __('attendance.fields.branch') }}: {{ __('app.common.all') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="health" :label="__('devices.fields.health')" hide-label class="md:w-44">
            <option value="">{{ __('devices.fields.health') }}: {{ __('app.common.all') }}</option>
            @foreach ($healthOptions as $option)
                <option value="{{ $option->value }}" @selected(($filters['health'] ?? '') === $option->value)>{{ $option->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('devices.index.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-56">{{ __('devices.fields.name') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.branch') }}</x-table.th>
                <x-table.th>{{ __('devices.fields.adapter') }}</x-table.th>
                <x-table.th>{{ __('devices.fields.connection_mode') }}</x-table.th>
                <x-table.th>{{ __('devices.fields.health') }}</x-table.th>
                <x-table.th>{{ __('devices.fields.last_sync') }}</x-table.th>
                <x-table.th>{{ __('devices.fields.mappings') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($devices as $device)
            @php $health = $device->health(); @endphp
            <x-table.row data-href="{{ route('admin.attendance-devices.show', $device) }}">
                <x-table.td :label="__('devices.fields.name')">
                    <a href="{{ route('admin.attendance-devices.show', $device) }}" class="block text-label-md text-text-link hover:underline">{{ $device->name }}</a>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $device->serial_number }}</span>
                </x-table.td>
                <x-table.td :label="__('attendance.fields.branch')">{{ $device->branch?->code ?? '—' }}@if ($device->location)<span class="block text-caption-md text-text-tertiary">{{ $device->location->name }}</span>@endif</x-table.td>
                <x-table.td :label="__('devices.fields.adapter')">
                    {{ $device->adapterLabel() }}
                    @if ($device->isSimulated()) @include('admin.attendance._simulated') @endif
                </x-table.td>
                <x-table.td :label="__('devices.fields.connection_mode')">{{ $device->connection_mode->label() }}</x-table.td>
                <x-table.td :label="__('devices.fields.health')"><x-badge :tone="$health->tone()" dot>{{ $health->label() }}</x-badge></x-table.td>
                <x-table.td :label="__('devices.fields.last_sync')">
                    @if ($device->latestSyncLog)
                        <x-badge :tone="$device->latestSyncLog->status->tone()">{{ $device->latestSyncLog->type->label() }} · {{ $device->latestSyncLog->status->label() }}</x-badge>
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $device->latestSyncLog->started_at->diffForHumans() }}</span>
                    @else
                        <span class="text-text-tertiary">{{ __('devices.show.never') }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('devices.fields.mappings')" class="tabular-nums">{{ $device->users_count }}</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="fingerprint" :title="__('devices.index.empty')" :description="__('devices.index.empty_help')" />
        @endforelse
    </x-table>
@endsection
