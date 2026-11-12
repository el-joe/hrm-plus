@extends('layouts.admin', ['subtitle' => __('assets.subtitle')])

@section('title', __('assets.title'))

@section('page-actions')
    @can('export', App\Models\Asset::class)
        <x-button variant="ghost" icon="download" :href="route('admin.assets.export', request()->query())">{{ __('assets.export_xlsx') }}</x-button>
        <x-button variant="ghost" icon="download" :href="route('admin.assets.export', [...request()->query(), 'format' => 'csv'])">{{ __('assets.export_csv') }}</x-button>
    @endcan
    @can('create', App\Models\Asset::class)
        <x-button icon="plus" :href="route('admin.assets.create')">{{ __('assets.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('assets.stats.total')" :value="$counts['total']" icon="laptop" />
        <x-stat-card :label="__('assets.stats.assigned')" :value="$counts['assigned']" icon="users" :href="route('admin.assets.index', ['status' => 'assigned'])" />
        <x-stat-card :label="__('assets.stats.available')" :value="$counts['available']" icon="check-circle" :href="route('admin.assets.index', ['status' => 'available'])" />
        <x-stat-card :label="__('assets.stats.value')" :value="App\Support\Money::format($counts['value'])" icon="coins" :hint="$currency" />
    </div>
    @if ($counts['attention'] > 0)
        <x-alert tone="warning">{{ __('assets.stats.attention') }}: {{ $counts['attention'] }}</x-alert>
    @endif

    <x-filter-bar :action="route('admin.assets.index')" :reset="route('admin.assets.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="category" :label="__('assets.fields.asset_category_id')" hide-label class="md:w-44">
            <option value="">{{ __('assets.fields.asset_category_id') }}: {{ __('assets.all') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" :label="__('assets.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('assets.fields.status') }}: {{ __('assets.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="branch" :label="__('assets.fields.branch_id')" hide-label class="md:w-44">
            <option value="">{{ __('assets.fields.branch_id') }}: {{ __('assets.all') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) ($filters['branch'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="warranty" :label="__('assets.fields.warranty')" hide-label class="md:w-52">
            <option value="">{{ __('assets.fields.warranty') }}: {{ __('assets.all') }}</option>
            <option value="expiring" @selected(($filters['warranty'] ?? '') === 'expiring')>{{ __('assets.filters.warranty_expiring') }}</option>
            <option value="expired" @selected(($filters['warranty'] ?? '') === 'expired')>{{ __('assets.filters.warranty_expired') }}</option>
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('assets.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="tag">{{ __('assets.fields.asset_tag') }}</x-table.th>
                <x-table.th sort="name" class="min-w-56">{{ __('assets.fields.name') }}</x-table.th>
                <x-table.th>{{ __('assets.fields.branch_id') }}</x-table.th>
                <x-table.th>{{ __('assets.fields.custodian') }}</x-table.th>
                <x-table.th sort="cost">{{ __('assets.fields.purchase_cost') }}</x-table.th>
                <x-table.th sort="warranty">{{ __('assets.fields.warranty_expires_on') }}</x-table.th>
                <x-table.th>{{ __('assets.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($assets as $asset)
            <x-table.row :data-href="route('admin.assets.show', $asset)">
                <x-table.td :label="__('assets.fields.asset_tag')" class="whitespace-nowrap">
                    <a href="{{ route('admin.assets.show', $asset) }}" class="text-label-md text-text-link hover:underline" dir="ltr">{{ $asset->asset_tag }}</a>
                    <span class="block text-caption-md text-text-tertiary">{{ $asset->category?->name }}</span>
                </x-table.td>
                <x-table.td :label="__('assets.fields.name')">
                    <span class="text-label-md text-text-primary">{{ $asset->name }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ trim($asset->brand.' '.$asset->model) }}@if ($asset->serial_number) · {{ $asset->serial_number }}@endif</span>
                </x-table.td>
                <x-table.td :label="__('assets.fields.branch_id')">{{ $asset->branch?->name ?? '—' }}</x-table.td>
                <x-table.td :label="__('assets.fields.custodian')">
                    @if ($asset->currentAssignment)
                        {{ $asset->currentAssignment->employee?->label() }}
                        @unless ($asset->currentAssignment->isAcknowledged())
                            <span class="block text-caption-md text-feedback-warning-text">{{ __('assets.assignments.not_acknowledged') }}</span>
                        @endunless
                    @else
                        <span class="text-text-tertiary">—</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('assets.fields.purchase_cost')">
                    @if ($asset->purchase_cost !== null)
                        @include('admin.payroll._money', ['amount' => $asset->purchase_cost, 'currency' => null])
                    @else
                        <span class="text-text-tertiary">—</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('assets.fields.warranty_expires_on')" class="whitespace-nowrap">
                    @if ($asset->warranty_expires_on)
                        <span dir="ltr" @class(['text-feedback-danger-text' => $asset->warranty_expires_on->isPast()])>{{ $asset->warranty_expires_on->toDateString() }}</span>
                    @else
                        <span class="text-text-tertiary">—</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('assets.fields.status')">
                    @include('admin.assets._status', ['status' => $asset->status])
                    <span class="mt-1 block">@include('admin.assets._condition', ['condition' => $asset->condition])</span>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="laptop" :title="__('assets.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$assets" />
        </x-slot:footer>
    </x-table>
@endsection
