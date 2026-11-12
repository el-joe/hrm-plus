@extends('layouts.admin', ['subtitle' => __('app.audit.subtitle')])

@section('title', __('app.audit.title'))

@section('content')
    <x-filter-bar :action="route('admin.audit-logs.index')" :reset="route('admin.audit-logs.index')" data-region="filters">
        <x-form.select name="user_id" :label="__('app.audit.user')" class="md:w-44">
            <option value="">{{ __('app.common.all') }}</option>
            @foreach ($admins as $admin)
                <option value="{{ $admin->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $admin->id)>{{ $admin->name }}</option>
            @endforeach
        </x-form.select>

        <x-form.select name="event" :label="__('app.audit.event')" class="md:w-56">
            <option value="">{{ __('app.common.all') }}</option>
            @foreach ($events as $event => $eventLabel)
                <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $eventLabel }}</option>
            @endforeach
        </x-form.select>

        <x-form.select name="auditable_type" :label="__('app.audit.record_type')" class="md:w-40">
            <option value="">{{ __('app.common.all') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(($filters['auditable_type'] ?? '') === $type)>{{ class_basename($type) }}</option>
            @endforeach
        </x-form.select>

        <x-form.input name="auditable_id" type="number" min="1" :label="__('app.audit.record_id')" :value="$filters['auditable_id'] ?? ''" class="md:w-28" />

        <x-form.input name="date_from" type="date" :label="__('app.common.date_from')" :value="$filters['date_from'] ?? ''" class="md:w-40" />

        <x-form.input name="date_to" type="date" :label="__('app.common.date_to')" :value="$filters['date_to'] ?? ''" class="md:w-40" />
    </x-filter-bar>

    <x-table data-region="table" :caption="__('app.audit.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('app.common.date') }}</x-table.th>
                <x-table.th>{{ __('app.audit.user') }}</x-table.th>
                <x-table.th>{{ __('app.audit.event') }}</x-table.th>
                <x-table.th>{{ __('app.audit.record') }}</x-table.th>
                <x-table.th>{{ __('app.common.ip_address') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($logs as $log)
            <x-table.row>
                <x-table.td :label="__('app.common.date')" class="whitespace-nowrap text-text-secondary">{{ $log->created_at?->format('Y-m-d H:i:s') }}</x-table.td>
                <x-table.td :label="__('app.audit.user')">
                    <div class="flex items-center gap-2">
                        <x-avatar :name="$log->user?->name ?? __('app.audit.system')" size="sm" />
                        <span class="truncate">{{ $log->user?->name ?? __('app.audit.system') }}</span>
                    </div>
                </x-table.td>
                <x-table.td :label="__('app.audit.event')">
                    <span class="block">{{ $log->label() }}</span>
                    <code class="text-caption-md text-text-tertiary" dir="ltr">{{ $log->event }}</code>
                </x-table.td>
                <x-table.td :label="__('app.audit.record')" class="text-text-secondary">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</x-table.td>
                <x-table.td :label="__('app.common.ip_address')" class="font-mono text-body-sm text-text-secondary" dir="ltr">{{ $log->ip_address }}</x-table.td>
                <x-table.td class="text-end">
                    <x-icon-button icon="eye" size="sm" :href="route('admin.audit-logs.show', $log)" :label="__('app.actions.view')" />
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="history" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$logs" />
        </x-slot:footer>
    </x-table>
@endsection
