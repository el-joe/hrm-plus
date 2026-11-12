@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('app.audit.title'), 'url' => route('admin.audit-logs.index')],
]])

@section('title', __('app.audit.entry', ['id' => $log->id]))

@section('content')
    <x-card :title="__('app.audit.summary')" data-section="summary">
        <x-detail-list>
            <x-detail-item :label="__('app.common.date')">{{ $log->created_at?->format('Y-m-d H:i:s') }}</x-detail-item>
            <x-detail-item :label="__('app.audit.user')">{{ $log->user?->name ?? __('app.audit.system') }}</x-detail-item>
            <x-detail-item :label="__('app.audit.event')">{{ $log->label() }} <code class="text-caption-md text-text-tertiary" dir="ltr">{{ $log->event }}</code></x-detail-item>
            <x-detail-item :label="__('app.audit.record')">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : __('app.common.none') }}</x-detail-item>
            <x-detail-item :label="__('app.common.ip_address')"><span dir="ltr">{{ $log->ip_address }}</span></x-detail-item>
            <x-detail-item :label="__('app.common.user_agent')">{{ $log->user_agent }}</x-detail-item>
            <x-detail-item :label="__('app.audit.url')" class="sm:col-span-2"><span dir="ltr" class="break-all">{{ $log->url }}</span></x-detail-item>
        </x-detail-list>
    </x-card>

    @php
        $changedAttributes = array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? [])));
    @endphp
    @if ($changedAttributes !== [])
        <section data-section="changes" class="flex flex-col gap-3">
            <h2 class="text-h4">{{ __('app.audit.changes') }}</h2>
            <x-table data-region="table" :caption="__('app.audit.changes')">
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('app.audit.attribute') }}</x-table.th>
                        <x-table.th>{{ __('app.audit.old_value') }}</x-table.th>
                        <x-table.th>{{ __('app.audit.new_value') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($changedAttributes as $attribute)
                    <x-table.row>
                        <th scope="row" class="h-14 px-4 text-start align-middle"><code class="text-body-sm">{{ $attribute }}</code></th>
                        @foreach ([$log->old_values[$attribute] ?? null, $log->new_values[$attribute] ?? null] as $index => $value)
                            <x-table.td :label="$index === 0 ? __('app.audit.old_value') : __('app.audit.new_value')"
                                @class(['break-all', 'text-feedback-danger-text' => $index === 0, 'text-feedback-success-text' => $index === 1])>{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</x-table.td>
                        @endforeach
                    </x-table.row>
                @endforeach
            </x-table>
        </section>
    @endif

    @if ($log->meta)
        <x-card :title="__('app.audit.meta')" data-section="meta">
            <pre dir="ltr" class="overflow-x-auto rounded-md bg-surface-subtle p-4 text-body-sm text-text-secondary">{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </x-card>
    @endif
@endsection
