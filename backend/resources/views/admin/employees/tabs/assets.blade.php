{{--
    Assets tab (Phase 8a, assets.view): what the employee holds now, returned / lost assets, custody events.
    Variables: $employee, $currentAssets, $pastAssets, $assetEvents, $currency.
--}}
<x-card :title="__('assets.tab.current')" padding="none" data-region="current-assets">
    @can('assets.update')
        <x-slot:actions>
            <x-button size="sm" variant="secondary" icon="user-plus" :href="route('admin.asset-assignments.create', ['employee' => $employee->id])">{{ __('assets.tab.hand_over') }}</x-button>
        </x-slot:actions>
    @endcan
    <x-table :caption="__('assets.tab.current')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('assets.fields.asset_id') }}</x-table.th>
                <x-table.th>{{ __('assets.fields.assigned_on') }}</x-table.th>
                <x-table.th>{{ __('assets.fields.expected_return_on') }}</x-table.th>
                <x-table.th>{{ __('assets.fields.acknowledged_at') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($currentAssets as $row)
            <x-table.row>
                <x-table.td :label="__('assets.fields.asset_id')">
                    @can('view', $row->asset)
                        <a href="{{ route('admin.assets.show', $row->asset) }}" class="text-label-md text-text-link hover:underline" dir="ltr">{{ $row->asset->asset_tag }}</a>
                    @else
                        <span class="text-label-md" dir="ltr">{{ $row->asset->asset_tag }}</span>
                    @endcan
                    <span class="block text-caption-md text-text-tertiary">{{ $row->asset->name }} · {{ $row->asset->category?->name }}</span>
                </x-table.td>
                <x-table.td :label="__('assets.fields.assigned_on')" class="whitespace-nowrap">
                    <span dir="ltr">{{ $row->assigned_on->toDateString() }}</span>
                    <span class="mt-1 block">@include('admin.assets._condition', ['condition' => $row->condition_on_issue])</span>
                </x-table.td>
                <x-table.td :label="__('assets.fields.expected_return_on')" class="whitespace-nowrap">
                    <span dir="ltr">{{ $row->expected_return_on?->toDateString() ?? '—' }}</span>
                    @if ($row->isOverdue()) <x-badge tone="danger">{{ __('assets.assignments.overdue') }}</x-badge> @endif
                </x-table.td>
                <x-table.td :label="__('assets.fields.acknowledged_at')">
                    @if ($row->acknowledged_at)
                        <x-badge tone="success">{{ $row->acknowledged_at->toDateString() }}</x-badge>
                    @else
                        <x-badge tone="warning">{{ __('assets.assignments.not_acknowledged') }}</x-badge>
                    @endif
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="4" icon="laptop" :title="__('assets.tab.none_current')" />
        @endforelse
    </x-table>
</x-card>

<x-card :title="__('assets.tab.past')" padding="none" data-region="past-assets">
    <x-table :caption="__('assets.tab.past')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('assets.fields.asset_id') }}</x-table.th>
                <x-table.th>{{ __('assets.fields.assigned_on') }}</x-table.th>
                <x-table.th>{{ __('assets.fields.returned_on') }}</x-table.th>
                <x-table.th>{{ __('assets.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($pastAssets as $row)
            <x-table.row>
                <x-table.td :label="__('assets.fields.asset_id')">
                    <span class="text-label-md" dir="ltr">{{ $row->asset->asset_tag }}</span>
                    <span class="block text-caption-md text-text-tertiary">{{ $row->asset->name }}</span>
                </x-table.td>
                <x-table.td :label="__('assets.fields.assigned_on')" class="whitespace-nowrap"><span dir="ltr">{{ $row->assigned_on->toDateString() }}</span></x-table.td>
                <x-table.td :label="__('assets.fields.returned_on')" class="whitespace-nowrap">
                    <span dir="ltr">{{ $row->returned_on?->toDateString() ?? '—' }}</span>
                    @if ($row->condition_on_return) <span class="mt-1 block">@include('admin.assets._condition', ['condition' => $row->condition_on_return])</span> @endif
                </x-table.td>
                <x-table.td :label="__('assets.fields.status')"><x-badge :tone="$row->status->tone()">{{ $row->status->label() }}</x-badge></x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="4" icon="history" :title="__('assets.tab.none_past')" />
        @endforelse
    </x-table>
</x-card>

@if ($assetEvents->isNotEmpty())
    <x-card :title="__('assets.tab.events')">
        <ul class="flex flex-col divide-y divide-border-subtle">
            @foreach ($assetEvents as $event)
                <li class="flex flex-wrap items-center gap-2 py-2 text-body-sm">
                    <span class="text-caption-md text-text-tertiary tabular-nums" dir="ltr">{{ $event->occurred_on->toDateString() }}</span>
                    <x-badge :tone="$event->type->tone()">{{ $event->type->label() }}</x-badge>
                    <span dir="ltr" class="text-text-primary">{{ $event->asset?->asset_tag }}</span>
                    @if ($event->note) <span class="text-text-secondary">· {{ $event->note }}</span> @endif
                </li>
            @endforeach
        </ul>
    </x-card>
@endif
