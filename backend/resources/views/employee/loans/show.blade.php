@extends('layouts.employee')

@section('title', $loan->type->label().' · '.$loan->reference)

@section('content')
    <div>
        <x-button variant="ghost" size="sm" icon="arrow-left" :href="route('portal.loans')">{{ __('payroll.loans.portal.back') }}</x-button>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <x-stat-card :label="__('payroll.loans.fields.amount')" :value="App\Support\Money::format($loan->approved_amount ?? $loan->requested_amount)" icon="wallet" :hint="$currency" />
        <x-stat-card :label="__('payroll.loans.fields.outstanding')" :value="App\Support\Money::format($loan->outstanding_amount)" icon="coins" :hint="$currency" />
        <x-stat-card :label="__('payroll.loans.fields.instalments')" :value="($loan->instalment_count ?? $loan->requested_instalments)" icon="calendar" :hint="($loan->start_date ?? $loan->requested_start)->format('Y-m')" />
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @include('admin.payroll._loan-status')
        @if ($loan->rejection_reason)
            <x-alert tone="danger" class="w-full">{{ __('payroll.loans.portal.rejected_because', ['reason' => $loan->rejection_reason]) }}</x-alert>
        @endif
    </div>

    <x-card :title="__('payroll.loans.schedule')" padding="none" data-region="my-schedule">
        <x-table :caption="__('payroll.loans.schedule')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('payroll.loans.fields.sequence') }}</x-table.th>
                    <x-table.th>{{ __('payroll.loans.fields.due_date') }}</x-table.th>
                    <x-table.th>{{ __('payroll.loans.fields.amount') }}</x-table.th>
                    <x-table.th>{{ __('payroll.loans.fields.status') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($loan->instalments as $instalment)
                <x-table.row>
                    <x-table.td :label="__('payroll.loans.fields.sequence')" class="tabular-nums">{{ $instalment->sequence }}</x-table.td>
                    <x-table.td :label="__('payroll.loans.fields.due_date')"><span dir="ltr">{{ $instalment->due_date->toDateString() }}</span></x-table.td>
                    <x-table.td :label="__('payroll.loans.fields.amount')">@include('admin.payroll._money', ['amount' => $instalment->amount])</x-table.td>
                    <x-table.td :label="__('payroll.loans.fields.status')">{{ $instalment->status->label() }}</x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="4" icon="calendar" :title="__('payroll.loans.schedule_after_approval')" />
            @endforelse
        </x-table>
    </x-card>

    @if ($loan->isPending())
        <x-card :title="__('payroll.loans.cancel_request')">
            <form method="POST" action="{{ route('portal.loans.cancel', $loan->id) }}" class="flex flex-col gap-2 sm:flex-row sm:items-end" data-confirm="{{ __('payroll.loans.confirm_cancel') }}">
                @csrf
                <x-form.input name="reason" :label="__('payroll.fields.reason')" maxlength="1000" class="sm:w-72" />
                <x-button variant="ghost" icon="x-circle">{{ __('payroll.loans.cancel_request') }}</x-button>
            </form>
        </x-card>
    @endif
@endsection
