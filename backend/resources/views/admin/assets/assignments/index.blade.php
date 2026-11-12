@extends('layouts.admin', ['subtitle' => __('assets.assignments.subtitle')])

@section('title', __('assets.assignments.title'))

@section('page-actions')
    @can('export', App\Models\AssetAssignment::class)
        <x-button variant="ghost" icon="download" :href="route('admin.asset-assignments.export', request()->query())">{{ __('assets.export_xlsx') }}</x-button>
    @endcan
    @can('create', App\Models\AssetAssignment::class)
        <x-button icon="plus" :href="route('admin.asset-assignments.create')">{{ __('assets.assignments.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('assets.stats.active')" :value="$counts['active']" icon="laptop" :href="route('admin.asset-assignments.index', ['status' => 'active'])" />
        <x-stat-card :label="__('assets.stats.holders')" :value="$counts['holders']" icon="users" />
        <x-stat-card :label="__('assets.stats.unacknowledged')" :value="$counts['unacknowledged']" icon="inbox" :href="route('admin.asset-assignments.index', ['flag' => 'unacknowledged'])" />
        <x-stat-card :label="__('assets.stats.overdue')" :value="$counts['overdue']" icon="clock" :href="route('admin.asset-assignments.index', ['flag' => 'overdue'])" />
    </div>

    <x-filter-bar :action="route('admin.asset-assignments.index')" :reset="route('admin.asset-assignments.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="status" :label="__('assets.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('assets.fields.status') }}: {{ __('assets.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="category" :label="__('assets.fields.asset_category_id')" hide-label class="md:w-44">
            <option value="">{{ __('assets.fields.asset_category_id') }}: {{ __('assets.all') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="flag" :label="__('assets.fields.flag')" hide-label class="md:w-48">
            <option value="">{{ __('assets.fields.flag') }}: {{ __('assets.all') }}</option>
            <option value="unacknowledged" @selected(($filters['flag'] ?? '') === 'unacknowledged')>{{ __('assets.filters.unacknowledged') }}</option>
            <option value="overdue" @selected(($filters['flag'] ?? '') === 'overdue')>{{ __('assets.filters.overdue') }}</option>
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('assets.assignments.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('assets.fields.asset_id') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('assets.fields.employee') }}</x-table.th>
                <x-table.th sort="assigned_on">{{ __('assets.fields.assigned_on') }}</x-table.th>
                <x-table.th sort="expected_return_on">{{ __('assets.fields.expected_return_on') }}</x-table.th>
                <x-table.th sort="returned_on">{{ __('assets.fields.returned_on') }}</x-table.th>
                <x-table.th>{{ __('assets.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($assignments as $row)
            <x-table.row>
                <x-table.td :label="__('assets.fields.asset_id')">
                    @can('view', $row->asset)
                        <a href="{{ route('admin.assets.show', $row->asset) }}" class="text-label-md text-text-link hover:underline" dir="ltr">{{ $row->asset->asset_tag }}</a>
                    @else
                        <span class="text-label-md" dir="ltr">{{ $row->asset->asset_tag }}</span>
                    @endcan
                    <span class="block text-caption-md text-text-tertiary">{{ $row->asset->name }} · {{ $row->asset->category?->name }}</span>
                </x-table.td>
                <x-table.td :label="__('assets.fields.employee')">{{ $row->employee?->label() }}</x-table.td>
                <x-table.td :label="__('assets.fields.assigned_on')" class="whitespace-nowrap">
                    <span dir="ltr">{{ $row->assigned_on->toDateString() }}</span>
                    <span class="mt-1 block">@include('admin.assets._condition', ['condition' => $row->condition_on_issue])</span>
                </x-table.td>
                <x-table.td :label="__('assets.fields.expected_return_on')" class="whitespace-nowrap">
                    @if ($row->expected_return_on)
                        <span dir="ltr">{{ $row->expected_return_on->toDateString() }}</span>
                        @if ($row->isOverdue()) <x-badge tone="danger">{{ __('assets.assignments.overdue') }}</x-badge> @endif
                    @else
                        <span class="text-text-tertiary">—</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('assets.fields.returned_on')" class="whitespace-nowrap">
                    @if ($row->returned_on)
                        <span dir="ltr">{{ $row->returned_on->toDateString() }}</span>
                        <span class="mt-1 block">@include('admin.assets._condition', ['condition' => $row->condition_on_return])</span>
                    @else
                        <span class="text-text-tertiary">—</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('assets.fields.status')">
                    <x-badge :tone="$row->status->tone()">{{ $row->status->label() }}</x-badge>
                    @if ($row->isActive())
                        <span @class(['block text-caption-md', 'text-text-tertiary' => $row->isAcknowledged(), 'text-feedback-warning-text' => ! $row->isAcknowledged()])>
                            {{ $row->acknowledged_at ? __('assets.assignments.acknowledged_on', ['date' => $row->acknowledged_at->toDateString()]) : __('assets.assignments.not_acknowledged') }}
                        </span>
                    @endif
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="users" :title="__('assets.assignments.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$assignments" />
        </x-slot:footer>
    </x-table>
@endsection
