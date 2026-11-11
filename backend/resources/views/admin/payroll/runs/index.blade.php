@extends('layouts.admin', ['subtitle' => __('payroll_runs.runs.subtitle')])

@section('title', __('payroll_runs.runs.title'))

@section('page-actions')
    @can('create', App\Models\PayrollRun::class)
        <x-button icon="plus" :href="route('admin.payroll-runs.create')">{{ __('payroll_runs.runs.new') }}</x-button>
    @endcan
@endsection

@section('content')
    @php($currency = setting('localisation.currency_code'))
    <div class="grid gap-3 sm:grid-cols-3">
        <x-stat-card :label="__('payroll_runs.runs.pending_approval')" :value="$counts['pending_approval']" icon="inbox" :href="route('admin.payroll-runs.index', ['status' => 'pending_approval'])" />
        <x-stat-card :label="__('payroll_runs.runs.in_progress')" :value="$counts['in_progress']" icon="refresh" />
        <x-stat-card :label="__('payroll_runs.runs.last_finalised')" :value="$counts['last_finalised'] ? App\Support\Money::format($counts['last_finalised']->net_total) : '—'" icon="coins"
            :hint="$counts['last_finalised'] ? $counts['last_finalised']->period->name.' · '.$currency : null" :href="$counts['last_finalised'] ? route('admin.payroll-runs.show', $counts['last_finalised']) : null" />
    </div>

    <x-filter-bar :action="route('admin.payroll-runs.index')" :reset="route('admin.payroll-runs.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="period" :label="__('payroll_runs.runs.fields.payroll_period_id')" hide-label class="md:w-52">
            <option value="">{{ __('payroll_runs.runs.fields.payroll_period_id') }}: {{ __('app.common.all') }}</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}" @selected((int) ($filters['period'] ?? 0) === $period->id)>{{ $period->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" :label="__('payroll_runs.runs.fields.status')" hide-label class="md:w-48">
            <option value="">{{ __('payroll_runs.runs.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="type" :label="__('payroll_runs.runs.fields.type')" hide-label class="md:w-48">
            <option value="">{{ __('payroll_runs.runs.fields.type') }}: {{ __('app.common.all') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('payroll_runs.runs.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('payroll_runs.runs.fields.reference') }}</x-table.th>
                <x-table.th class="min-w-56">{{ __('payroll_runs.runs.fields.name') }}</x-table.th>
                <x-table.th>{{ __('payroll_runs.runs.fields.payroll_period_id') }}</x-table.th>
                <x-table.th>{{ __('payroll_runs.runs.fields.employees') }}</x-table.th>
                <x-table.th>{{ __('payroll_runs.runs.fields.net') }}</x-table.th>
                <x-table.th>{{ __('payroll_runs.runs.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($runs as $run)
            <x-table.row :data-href="route('admin.payroll-runs.show', $run)">
                <x-table.td :label="__('payroll_runs.runs.fields.reference')" class="whitespace-nowrap">
                    <a href="{{ route('admin.payroll-runs.show', $run) }}" class="text-label-md text-text-link hover:underline" dir="ltr">{{ $run->reference }}</a>
                    <span class="block text-caption-md text-text-tertiary">{{ $run->type->label() }}</span>
                </x-table.td>
                <x-table.td :label="__('payroll_runs.runs.fields.name')">
                    {{ $run->name }}
                    <span class="block text-caption-md text-text-tertiary">{{ $run->branch?->name ?? __('payroll_runs.runs.consolidated') }}</span>
                </x-table.td>
                <x-table.td :label="__('payroll_runs.runs.fields.payroll_period_id')" class="whitespace-nowrap">{{ $run->period->name }}</x-table.td>
                <x-table.td :label="__('payroll_runs.runs.fields.employees')" class="tabular-nums">{{ $run->visible_employees }}</x-table.td>
                <x-table.td :label="__('payroll_runs.runs.fields.net')">@include('admin.payroll._money', ['amount' => $run->visible_net ?? '0', 'currency' => $currency])</x-table.td>
                <x-table.td :label="__('payroll_runs.runs.fields.status')">@include('admin.payroll.runs._status')</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="coins" :title="__('payroll_runs.runs.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$runs" />
        </x-slot:footer>
    </x-table>
@endsection
