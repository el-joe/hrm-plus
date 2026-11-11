@php $tz = $device->effectiveTimezone(); @endphp
@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('devices.index.title'), 'url' => route('admin.attendance-devices.index')]],
    'subtitle' => $device->serial_number.' · '.$device->adapterLabel(),
])

@section('title', $device->name)

@section('page-actions')
    @can('manage', $device)
        <x-button variant="secondary" icon="upload" :href="route('admin.attendance-devices.import', $device)">{{ __('devices.import.title') }}</x-button>
    @endcan
    @can('update', $device)
        <form method="POST" action="{{ route('admin.attendance-devices.status', $device) }}" data-confirm="{{ $device->is_active ? __('devices.show.confirm_deactivate') : __('devices.show.confirm_activate') }}" data-confirm-tone="warning">
            @csrf @method('PATCH')
            <x-button variant="secondary" icon="power">{{ $device->is_active ? __('devices.show.deactivate') : __('devices.show.activate') }}</x-button>
        </form>
        <x-button variant="secondary" icon="pencil" :href="route('admin.attendance-devices.edit', $device)">{{ __('app.actions.edit') }}</x-button>
    @endcan
    @can('delete', $device)
        <form method="POST" action="{{ route('admin.attendance-devices.destroy', $device) }}" data-confirm="{{ __('devices.show.confirm_delete') }}" data-confirm-tone="danger" data-confirm-label="{{ __('app.actions.delete') }}">
            @csrf @method('DELETE')
            <x-button variant="danger" icon="trash">{{ __('app.actions.delete') }}</x-button>
        </form>
    @endcan
@endsection

@section('content')
    @if ($device->isSimulated())
        <x-alert tone="warning" :title="__('devices.simulated.title')">{{ __('devices.simulated.help') }}</x-alert>
    @endif

    @if ($token)
        <x-alert tone="success" :title="__('devices.show.token_title')" data-region="push-token">
            <p>{{ __('devices.show.token_help') }}</p>
            <code class="mt-2 block break-all rounded-md bg-surface-muted p-2 text-label-md" dir="ltr">{{ $token }}</code>
        </x-alert>
    @endif

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" data-region="device-state">
        <div class="flex flex-col gap-2 rounded-xl border border-border-subtle bg-surface-default p-5 shadow-xs">
            <span class="text-label-md text-text-secondary">{{ __('devices.fields.health') }}</span>
            <x-badge :tone="$health->tone()" dot>{{ $health->label() }}</x-badge>
            @if ($device->last_error)<span class="text-caption-md text-feedback-danger-text">{{ $device->last_error }}</span>@endif
        </div>
        <x-stat-card :label="__('devices.fields.last_sync')" :value="$device->last_sync_at?->diffForHumans() ?? __('devices.show.never')" icon="refresh" :hint="$device->last_sync_status?->label()" />
        <x-stat-card :label="__('devices.fields.last_seen')" :value="$device->last_seen_at?->diffForHumans() ?? __('devices.show.never')" icon="wifi" />
        <x-stat-card :label="__('devices.fields.failures')" :value="(string) $device->consecutive_failures" icon="alert-triangle"
            :hint="$device->next_retry_at ? __('devices.show.next_retry', ['time' => $device->next_retry_at->setTimezone($tz)->format('Y-m-d H:i')]) : null" />
        <x-stat-card :label="__('devices.fields.events')" :value="(string) $eventsCount" icon="inbox" :href="route('admin.attendance.events.index', ['device_id' => $device->id])" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('devices.show.details')" class="lg:col-span-1">
            <x-detail-list>
                <x-detail-item :label="__('devices.fields.serial_number')"><span dir="ltr">{{ $device->serial_number }}</span></x-detail-item>
                <x-detail-item :label="__('attendance.fields.branch')">{{ $device->branch ? $device->branch->code.' · '.$device->branch->name : '—' }}</x-detail-item>
                <x-detail-item :label="__('devices.fields.location')">{{ $device->location?->name ?? '—' }}</x-detail-item>
                <x-detail-item :label="__('devices.fields.adapter')">{{ $device->adapterLabel() }} @if ($device->isSimulated()) @include('admin.attendance._simulated') @endif</x-detail-item>
                <x-detail-item :label="__('devices.fields.connection_mode')">{{ $device->connection_mode->label() }}</x-detail-item>
                @if ($device->host)
                    <x-detail-item :label="__('devices.fields.host')"><span dir="ltr">{{ $device->host }}{{ $device->port ? ':'.$device->port : '' }}</span></x-detail-item>
                @endif
                <x-detail-item :label="__('attendance.fields.timezone')"><span dir="ltr">{{ $device->effectiveTimezone() }}</span></x-detail-item>
                <x-detail-item :label="__('devices.fields.pull_interval_minutes')">{{ $device->pull_interval_minutes }}</x-detail-item>
                <x-detail-item :label="__('devices.fields.credentials')">{{ $device->getRawOriginal('credentials') ? __('devices.show.stored_encrypted') : '—' }}</x-detail-item>
                @if ($device->connection_mode->value === 'push')
                    <x-detail-item :label="__('devices.fields.push_url')"><span class="break-all text-caption-md" dir="ltr">{{ $pushUrl }}</span></x-detail-item>
                    <x-detail-item :label="__('devices.fields.push_token')"><span dir="ltr">{{ $device->push_token_hint ? '••••'.$device->push_token_hint : '—' }}</span></x-detail-item>
                @endif
                <x-detail-item :label="__('devices.fields.status')">
                    <x-badge :tone="$device->is_active ? 'success' : 'neutral'">{{ $device->is_active ? __('app.user_statuses.active') : __('app.user_statuses.inactive') }}</x-badge>
                </x-detail-item>
            </x-detail-list>
        </x-card>

        @can('manage', $device)
            <x-card :title="__('devices.show.operations')" :subtitle="__('devices.show.operations_help')" class="lg:col-span-2" data-region="operations">
                <div class="flex flex-wrap gap-3">
                    @if ($device->is_active && $device->connection_mode->value === 'pull')
                        <form method="POST" action="{{ route('admin.attendance-devices.sync', $device) }}">
                            @csrf
                            <x-button icon="refresh">{{ __('devices.show.sync_now') }}</x-button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.attendance-devices.test', $device) }}">
                        @csrf
                        <x-button variant="secondary" icon="wifi">{{ __('devices.show.test') }}</x-button>
                    </form>
                    @if ($device->connection_mode->value === 'push')
                        <form method="POST" action="{{ route('admin.attendance-devices.token', $device) }}" data-confirm="{{ __('devices.show.confirm_token') }}" data-confirm-tone="warning">
                            @csrf
                            <x-button variant="secondary" icon="key">{{ __('devices.show.rotate_token') }}</x-button>
                        </form>
                    @endif
                </div>

                @if ($device->is_active && $device->connection_mode->value === 'pull')
                    <form method="POST" action="{{ route('admin.attendance-devices.historical', $device) }}" class="mt-5 grid gap-4 border-t border-border-subtle pt-4 sm:grid-cols-3 sm:items-end" data-validate>
                        @csrf
                        <p class="text-body-sm text-text-secondary sm:col-span-3">{{ __('devices.historical.help', ['days' => config('attendance_devices.max_historical_days')]) }}</p>
                        <x-form.input name="from" type="date" :label="__('attendance.fields.from')" :value="old('from')" required />
                        <x-form.input name="to" type="date" :label="__('attendance.fields.to')" :value="old('to')" required />
                        <div><x-button variant="secondary" icon="history">{{ __('devices.historical.run') }}</x-button></div>
                    </form>
                @endif
            </x-card>
        @endcan
    </div>

    @can('manage', $device)
        <x-card :title="__('devices.mappings.bulk_title')" :subtitle="__('devices.mappings.bulk_help')" data-region="bulk-mapping">
            <form method="POST" action="{{ route('admin.attendance-devices.mappings.store', $device) }}" class="flex flex-col gap-4">
                @csrf
                @error('rows') <x-alert tone="danger">{{ $message }}</x-alert> @enderror
                <div class="overflow-x-auto">
                    <table class="w-full min-w-140 text-body-sm">
                        <thead>
                            <tr class="border-b border-border-subtle text-start text-label-sm text-text-secondary">
                                <th class="px-2 py-2 text-start">{{ __('devices.fields.device_user_id') }}</th>
                                <th class="px-2 py-2 text-start">{{ __('devices.mappings.waiting') }}</th>
                                <th class="px-2 py-2 text-start">{{ __('devices.fields.employee') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($unknownUsers as $index => $unknown)
                                <tr class="border-b border-border-subtle">
                                    <td class="px-2 py-2">
                                        <input type="hidden" name="rows[{{ $index }}][device_user_id]" value="{{ $unknown->device_user_id }}">
                                        <span class="text-label-md" dir="ltr">{{ $unknown->device_user_id }}</span>
                                    </td>
                                    <td class="px-2 py-2 tabular-nums">{{ trans_choice('devices.mappings.events', $unknown->events, ['count' => $unknown->events]) }}</td>
                                    <td class="px-2 py-2">
                                        <x-form.select name="rows[{{ $index }}][employee_id]" :id="'map-'.$index" :label="__('devices.fields.employee')" hide-label>
                                            <option value="">{{ __('devices.mappings.leave_unmapped') }}</option>
                                            @foreach ($employees as $employee)
                                                <option value="{{ $employee->id }}">{{ $employee->label() }}</option>
                                            @endforeach
                                        </x-form.select>
                                    </td>
                                </tr>
                            @endforeach
                            @for ($extra = $unknownUsers->count(); $extra < $unknownUsers->count() + 3; $extra++)
                                <tr class="border-b border-border-subtle">
                                    <td class="px-2 py-2"><x-form.input name="rows[{{ $extra }}][device_user_id]" :id="'map-user-'.$extra" :label="__('devices.fields.device_user_id')" hide-label maxlength="64" dir="ltr" :placeholder="__('devices.fields.device_user_id')" /></td>
                                    <td class="px-2 py-2 text-text-tertiary">—</td>
                                    <td class="px-2 py-2">
                                        <x-form.select name="rows[{{ $extra }}][employee_id]" :id="'map-'.$extra" :label="__('devices.fields.employee')" hide-label>
                                            <option value="">—</option>
                                            @foreach ($employees as $employee)
                                                <option value="{{ $employee->id }}">{{ $employee->label() }}</option>
                                            @endforeach
                                        </x-form.select>
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <x-form.select name="scope" :label="__('devices.mappings.scope')" class="sm:w-72">
                        <option value="device">{{ __('devices.mappings.scope_device') }}</option>
                        <option value="all">{{ __('devices.mappings.scope_all') }}</option>
                    </x-form.select>
                    <x-button icon="check">{{ __('devices.mappings.save') }}</x-button>
                </div>
            </form>
        </x-card>
    @endcan

    <x-card :title="__('devices.mappings.title')" :subtitle="__('devices.mappings.help')" padding="none" data-region="mappings">
        <x-table :caption="__('devices.mappings.title')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('devices.fields.device_user_id') }}</x-table.th>
                    <x-table.th>{{ __('devices.fields.employee') }}</x-table.th>
                    <x-table.th>{{ __('devices.mappings.scope') }}</x-table.th>
                    <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($mappings->concat($globalMappings) as $mapping)
                <x-table.row>
                    <x-table.td :label="__('devices.fields.device_user_id')"><span class="text-label-md" dir="ltr">{{ $mapping->device_user_id }}</span></x-table.td>
                    <x-table.td :label="__('devices.fields.employee')">{{ $mapping->employee?->label() ?? '—' }}</x-table.td>
                    <x-table.td :label="__('devices.mappings.scope')">
                        <x-badge :tone="$mapping->appliesToAllDevices() ? 'info' : 'neutral'">{{ $mapping->appliesToAllDevices() ? __('devices.mappings.scope_all') : __('devices.mappings.scope_device') }}</x-badge>
                    </x-table.td>
                    <x-table.td>
                        @can('manage', $device)
                            <form method="POST" action="{{ route('admin.attendance-devices.mappings.destroy', [$device, $mapping]) }}" class="flex justify-end"
                                data-confirm="{{ __('devices.mappings.confirm_remove') }}" data-confirm-tone="warning">
                                @csrf @method('DELETE')
                                <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('devices.mappings.remove')" />
                            </form>
                        @endcan
                    </x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="4" icon="users" :title="__('devices.mappings.empty')" />
            @endforelse
        </x-table>
    </x-card>

    <x-card :title="__('devices.logs.title')" padding="none" data-region="sync-logs">
        <x-table :caption="__('devices.logs.title')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('devices.logs.started') }}</x-table.th>
                    <x-table.th>{{ __('devices.logs.type') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.status') }}</x-table.th>
                    <x-table.th>{{ __('devices.logs.fetched') }}</x-table.th>
                    <x-table.th>{{ __('devices.logs.created') }}</x-table.th>
                    <x-table.th>{{ __('devices.logs.duplicates') }}</x-table.th>
                    <x-table.th>{{ __('devices.logs.unmatched') }}</x-table.th>
                    <x-table.th>{{ __('devices.logs.message') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($logs as $log)
                <x-table.row>
                    <x-table.td :label="__('devices.logs.started')" class="tabular-nums">
                        <span dir="ltr">{{ $log->started_at->setTimezone($tz)->format('Y-m-d H:i:s') }}</span>
                        <span class="block text-caption-md text-text-tertiary">{{ $log->triggeredBy?->name ?? __('devices.logs.automatic') }}</span>
                    </x-table.td>
                    <x-table.td :label="__('devices.logs.type')">
                        {{ $log->type->label() }}
                        @if ($log->is_simulated) @include('admin.attendance._simulated') @endif
                        @if ($log->range_from)<span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $log->range_from->setTimezone($tz)->format('Y-m-d') }} – {{ $log->range_to?->setTimezone($tz)->format('Y-m-d') }}</span>@endif
                        @if ($log->file_name)<span class="block text-caption-md text-text-tertiary">{{ $log->file_name }}</span>@endif
                    </x-table.td>
                    <x-table.td :label="__('attendance.fields.status')"><x-badge :tone="$log->status->tone()">{{ $log->status->label() }}</x-badge>
                        @if ($log->attempt > 1)<span class="block text-caption-md text-text-tertiary">{{ __('devices.logs.attempt', ['n' => $log->attempt]) }}</span>@endif
                    </x-table.td>
                    <x-table.td :label="__('devices.logs.fetched')" class="tabular-nums">{{ $log->fetched }}</x-table.td>
                    <x-table.td :label="__('devices.logs.created')" class="tabular-nums">{{ $log->created }}</x-table.td>
                    <x-table.td :label="__('devices.logs.duplicates')" class="tabular-nums">{{ $log->duplicates }}</x-table.td>
                    <x-table.td :label="__('devices.logs.unmatched')" class="tabular-nums">{{ $log->unmatched }}</x-table.td>
                    <x-table.td :label="__('devices.logs.message')"><span class="text-caption-md text-text-secondary">{{ $log->message ?? '—' }}</span></x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="8" icon="refresh" :title="__('devices.logs.empty')" />
            @endforelse
            <x-slot:footer>
                <x-pagination :paginator="$logs" />
            </x-slot:footer>
        </x-table>
    </x-card>
@endsection
