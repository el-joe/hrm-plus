@extends('layouts.admin', ['subtitle' => __('payroll.loans.subtitle')])

@section('title', __('payroll.loans.title'))

@section('page-actions')
    @can('export', App\Models\EmployeeLoan::class)
        <x-button variant="ghost" icon="download" :href="route('admin.loans.export', request()->query())">{{ __('payroll.common.export_xlsx') }}</x-button>
    @endcan
    @can('create', App\Models\EmployeeLoan::class)
        <x-button icon="plus" :href="route('admin.loans.create')">{{ __('payroll.loans.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <div class="grid gap-3 sm:grid-cols-3">
        <x-stat-card :label="__('payroll.loans.pending')" :value="$counts['pending']" icon="inbox" :href="route('admin.loans.index', ['status' => 'pending'])" />
        <x-stat-card :label="__('payroll.loans.active')" :value="$counts['active']" icon="wallet" :href="route('admin.loans.index', ['status' => 'active'])" />
        <x-stat-card :label="__('payroll.loans.outstanding_total')" :value="App\Support\Money::format($counts['outstanding'])" icon="coins" :hint="setting('localisation.currency_code')" />
    </div>

    <x-filter-bar :action="route('admin.loans.index')" :reset="route('admin.loans.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="status" :label="__('payroll.loans.fields.status')" hide-label class="md:w-48">
            <option value="">{{ __('payroll.loans.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="type" :label="__('payroll.loans.fields.type')" hide-label class="md:w-44">
            <option value="">{{ __('payroll.loans.fields.type') }}: {{ __('app.common.all') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('payroll.loans.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('payroll.loans.fields.reference') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('payroll.loans.fields.employee') }}</x-table.th>
                <x-table.th>{{ __('payroll.loans.fields.amount') }}</x-table.th>
                <x-table.th>{{ __('payroll.loans.fields.instalments') }}</x-table.th>
                <x-table.th>{{ __('payroll.loans.fields.outstanding') }}</x-table.th>
                <x-table.th>{{ __('payroll.loans.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($loans as $loan)
            <x-table.row :data-href="route('admin.loans.show', $loan)">
                <x-table.td :label="__('payroll.loans.fields.reference')" class="whitespace-nowrap">
                    <a href="{{ route('admin.loans.show', $loan) }}" class="text-label-md text-text-link hover:underline" dir="ltr">{{ $loan->reference }}</a>
                    <span class="block text-caption-md text-text-tertiary">{{ $loan->type->label() }}</span>
                </x-table.td>
                <x-table.td :label="__('payroll.loans.fields.employee')">{{ $loan->employee->label() }}</x-table.td>
                <x-table.td :label="__('payroll.loans.fields.amount')">@include('admin.payroll._money', ['amount' => $loan->approved_amount ?? $loan->requested_amount])</x-table.td>
                <x-table.td :label="__('payroll.loans.fields.instalments')" class="tabular-nums">
                    {{ $loan->instalment_count ?? $loan->requested_instalments }}
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ ($loan->start_date ?? $loan->requested_start)->format('Y-m') }}</span>
                </x-table.td>
                <x-table.td :label="__('payroll.loans.fields.outstanding')">@include('admin.payroll._money', ['amount' => $loan->outstanding_amount])</x-table.td>
                <x-table.td :label="__('payroll.loans.fields.status')">
                    @include('admin.payroll._loan-status')
                    @if ($loan->isPending() && $loan->latestApprovalRequest?->currentStep)
                        <span class="block text-caption-md text-text-tertiary">{{ __('payroll.common.at_step', ['step' => $loan->latestApprovalRequest->currentStep->name]) }}</span>
                    @endif
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="wallet" :title="__('payroll.loans.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$loans" />
        </x-slot:footer>
    </x-table>
@endsection
