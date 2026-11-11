@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('payroll.loans.title'), 'url' => route('admin.loans.index')]],
    'subtitle' => $loan->employee->label(),
])

@section('title', $loan->reference.' · '.$loan->type->label())

@section('content')
    @php($currency = setting('localisation.currency_code'))
    @if ($errors->any())
        <x-alert tone="danger">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        <x-stat-card :label="__('payroll.loans.fields.amount')" :value="App\Support\Money::format($loan->approved_amount ?? $loan->requested_amount)" icon="wallet" :hint="$currency" />
        <x-stat-card :label="__('payroll.loans.fields.outstanding')" :value="App\Support\Money::format($loan->outstanding_amount)" icon="coins" :hint="$currency" />
        <x-stat-card :label="__('payroll.loans.fields.instalments')" :value="($loan->instalment_count ?? $loan->requested_instalments)" icon="calendar"
            :hint="($loan->start_date ?? $loan->requested_start)->format('Y-m')" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-card :title="__('payroll.loans.terms')">
                <x-slot:actions>@include('admin.payroll._loan-status')</x-slot:actions>
                <x-detail-list>
                    <x-detail-item :label="__('payroll.loans.fields.employee')">
                        @can('view', $loan->employee)
                            <a class="text-text-link hover:underline" href="{{ route('admin.employees.show', $loan->employee) }}">{{ $loan->employee->label() }}</a>
                        @else
                            {{ $loan->employee->label() }}
                        @endcan
                    </x-detail-item>
                    <x-detail-item :label="__('payroll.loans.requested')">
                        @include('admin.payroll._money', ['amount' => $loan->requested_amount, 'currency' => $currency]) ·
                        {{ $loan->requested_instalments }} × <span dir="ltr">{{ $loan->requested_start->format('Y-m') }}</span>
                    </x-detail-item>
                    @if ($loan->approved_amount !== null)
                        <x-detail-item :label="__('payroll.loans.approved_terms')">
                            @include('admin.payroll._money', ['amount' => $loan->approved_amount, 'currency' => $currency]) ·
                            {{ $loan->instalment_count }} × <span dir="ltr">{{ $loan->start_date?->format('Y-m') }}</span>
                        </x-detail-item>
                    @endif
                    <x-detail-item :label="__('payroll.loans.fields.reason')">{{ $loan->reason ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('payroll.loans.source')">{{ $loan->source->label() }} · {{ $loan->requester?->name }}</x-detail-item>
                    @if ($loan->rejection_reason)
                        <x-detail-item :label="__('payroll.common.reject_reason')">{{ $loan->rejection_reason }}</x-detail-item>
                    @endif
                    @if ($loan->approved_amount !== null)
                        <x-detail-item :label="__('payroll.loans.fields.outstanding')">
                            {{ __('payroll.loans.progress', ['paid' => App\Support\Money::format($loan->repaidAmount()), 'total' => App\Support\Money::format($loan->approved_amount)]) }}
                        </x-detail-item>
                    @endif
                </x-detail-list>
            </x-card>

            <x-card :title="__('payroll.loans.schedule')" padding="none" data-region="schedule">
                <x-table :caption="__('payroll.loans.schedule')">
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('payroll.loans.fields.sequence') }}</x-table.th>
                            <x-table.th>{{ __('payroll.loans.fields.due_date') }}</x-table.th>
                            <x-table.th>{{ __('payroll.loans.fields.amount') }}</x-table.th>
                            <x-table.th>{{ __('payroll.loans.fields.paid_amount') }}</x-table.th>
                            <x-table.th>{{ __('payroll.loans.fields.status') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($loan->instalments as $instalment)
                        <x-table.row>
                            <x-table.td :label="__('payroll.loans.fields.sequence')" class="tabular-nums">{{ $instalment->sequence }}</x-table.td>
                            <x-table.td :label="__('payroll.loans.fields.due_date')" class="whitespace-nowrap"><span dir="ltr">{{ $instalment->due_date->toDateString() }}</span></x-table.td>
                            <x-table.td :label="__('payroll.loans.fields.amount')">@include('admin.payroll._money', ['amount' => $instalment->amount])</x-table.td>
                            <x-table.td :label="__('payroll.loans.fields.paid_amount')">@include('admin.payroll._money', ['amount' => $instalment->paid_amount])</x-table.td>
                            <x-table.td :label="__('payroll.loans.fields.status')">
                                <x-badge :tone="match ($instalment->status->value) { 'scheduled' => 'info', 'deducted', 'repaid' => 'success', 'skipped' => 'warning', default => 'neutral' }">{{ $instalment->status->label() }}</x-badge>
                                @if ($instalment->is_final_settlement) <x-badge tone="warning">{{ __('payroll.loans.final_settlement') }}</x-badge> @endif
                                @if ($instalment->isScheduled() && $instalment->due_date->lt(now()->startOfMonth())) <x-badge tone="danger">{{ __('payroll.loans.overdue') }}</x-badge> @endif
                            </x-table.td>
                        </x-table.row>
                    @empty
                        <x-table.empty :colspan="5" icon="calendar" :title="__('payroll.loans.schedule_after_approval')" />
                    @endforelse
                </x-table>
            </x-card>

            <x-card :title="__('payroll.loans.ledger')" padding="none" data-region="ledger">
                <x-table :caption="__('payroll.loans.ledger')">
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('payroll.fields.date') }}</x-table.th>
                            <x-table.th>{{ __('payroll.loans.fields.type') }}</x-table.th>
                            <x-table.th>{{ __('payroll.loans.fields.amount') }}</x-table.th>
                            <x-table.th>{{ __('payroll.loans.fields.balance_after') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($loan->transactions as $row)
                        <x-table.row>
                            <x-table.td :label="__('payroll.fields.date')" class="whitespace-nowrap"><span dir="ltr">{{ $row->occurred_on->toDateString() }}</span></x-table.td>
                            <x-table.td :label="__('payroll.loans.fields.type')">
                                {{ $row->type->label() }}
                                @if ($row->note) <span class="block text-caption-md text-text-tertiary">{{ $row->note }}</span> @endif
                                <span class="block text-caption-md text-text-tertiary">{{ $row->creator?->name }}</span>
                            </x-table.td>
                            <x-table.td :label="__('payroll.loans.fields.amount')">@include('admin.payroll._money', ['amount' => $row->amount])</x-table.td>
                            <x-table.td :label="__('payroll.loans.fields.balance_after')">@include('admin.payroll._money', ['amount' => $row->balance_after])</x-table.td>
                        </x-table.row>
                    @empty
                        <x-table.empty :colspan="4" icon="coins" :title="__('payroll.loans.ledger_empty')" />
                    @endforelse
                </x-table>
            </x-card>

            <x-card :title="__('payroll.loans.changes')" data-region="changes">
                <ul class="flex flex-col divide-y divide-border-subtle">
                    @forelse ($loan->changes as $change)
                        <li class="flex flex-col gap-2 py-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-label-md text-text-primary">{{ $change->type->label() }}</span>
                                @include('admin.payroll._request-status', ['status' => $change->status])
                            </div>
                            <p class="text-body-sm text-text-secondary">
                                @if ($change->type === App\Enums\LoanChangeType::Skip)
                                    #{{ $change->instalment?->sequence }} · <span dir="ltr">{{ $change->instalment?->due_date->toDateString() }}</span>
                                @else
                                    {{ $change->new_instalment_count }} × <span dir="ltr">{{ $change->new_start_date?->format('Y-m') }}</span>
                                @endif
                                · {{ $change->reason }} · {{ $change->requester?->name }}
                            </p>
                            @if ($change->rejection_reason)
                                <p class="text-caption-md text-feedback-danger-text">{{ $change->rejection_reason }}</p>
                            @endif
                            @can('decide', $change)
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                    <form method="POST" action="{{ route('admin.loans.changes.decide', [$loan, $change, 'approve']) }}">
                                        @csrf
                                        <x-button size="sm" icon="check">{{ __('payroll.common.approve') }}</x-button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.loans.changes.decide', [$loan, $change, 'reject']) }}" class="flex items-end gap-2" data-validate>
                                        @csrf
                                        <x-form.input name="reason" :id="'change-reject-'.$change->id" :label="__('payroll.common.reject_reason')" hide-label required maxlength="1000" :placeholder="__('payroll.common.reject_reason')" />
                                        <x-button size="sm" variant="danger" icon="x-circle">{{ __('payroll.common.reject') }}</x-button>
                                    </form>
                                </div>
                            @endcan
                        </li>
                    @empty
                        <li class="py-2 text-body-sm text-text-tertiary">{{ __('payroll.loans.no_changes') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            @if ($canDecide)
                <x-card :title="__('payroll.common.decide')" :subtitle="__('payroll.loans.approve_terms_help')" data-region="decision">
                    <p class="mb-3 text-caption-md text-text-tertiary">@include('admin.payroll.loans._limits', ['limit' => $limits])</p>
                    <form method="POST" action="{{ route('admin.loans.decide', [$loan, 'approve']) }}" class="flex flex-col gap-3" data-validate>
                        @csrf
                        <x-form.input type="number" name="approved_amount" step="0.01" min="0.01" :max="(string) $loan->requested_amount" dir="ltr" :label="__('payroll.loans.fields.approved_amount')" :value="old('approved_amount', $loan->approved_amount ?? $loan->requested_amount)" />
                        <x-form.input type="number" name="instalment_count" min="1" max="120" :label="__('payroll.loans.fields.instalment_count')" :value="old('instalment_count', $loan->instalment_count ?? $loan->requested_instalments)" />
                        <x-form.input type="month" name="start_month" :label="__('payroll.loans.fields.start_month')" :value="old('start_month', ($loan->start_date ?? $loan->requested_start)->format('Y-m'))" />
                        <x-form.input name="reason" id="approve-reason" :label="__('payroll.common.comment')" maxlength="1000" />
                        <x-button icon="check">{{ __('payroll.common.approve') }}</x-button>
                    </form>
                    <form method="POST" action="{{ route('admin.loans.decide', [$loan, 'reject']) }}" class="mt-4 flex flex-col gap-2" data-validate>
                        @csrf
                        <x-form.textarea name="reason" id="reject-reason" :label="__('payroll.common.reject_reason')" required maxlength="1000" rows="2" />
                        <x-button variant="danger" icon="x-circle">{{ __('payroll.common.reject') }}</x-button>
                    </form>
                </x-card>
            @endif

            @can('cancel', $loan)
                <x-card :title="__('payroll.loans.cancel_request')">
                    <form method="POST" action="{{ route('admin.loans.cancel', $loan) }}" class="flex flex-col gap-2" data-validate data-confirm="{{ __('payroll.loans.confirm_cancel') }}" data-confirm-tone="danger">
                        @csrf
                        <x-form.input name="reason" id="cancel-reason" required maxlength="1000" :label="__('payroll.fields.reason')" />
                        <x-button variant="danger" icon="x">{{ __('payroll.loans.cancel_request') }}</x-button>
                    </form>
                </x-card>
            @endcan

            @if ($canUpdate)
                <x-card :title="__('payroll.loans.repay')" :subtitle="__('payroll.loans.repay_help')" data-region="repay">
                    <form method="POST" action="{{ route('admin.loans.repay', $loan) }}" class="flex flex-col gap-3" data-validate>
                        @csrf
                        <x-form.input type="number" name="amount" step="0.01" min="0.01" :max="(string) $loan->outstanding_amount" dir="ltr" required :label="__('payroll.loans.fields.amount')" :value="old('amount', $loan->outstanding_amount)" />
                        <x-form.input type="date" name="paid_on" required :label="__('payroll.loans.fields.paid_on')" :value="old('paid_on', now()->toDateString())" :max="now()->toDateString()" />
                        <x-form.input name="note" id="repay-note" maxlength="1000" :label="__('payroll.loans.fields.note')" />
                        <x-button variant="secondary" icon="check">{{ __('payroll.loans.repay') }}</x-button>
                    </form>
                </x-card>

                <x-card :title="__('payroll.loans.request_change')" :subtitle="__('payroll.loans.change_help')" data-region="change">
                    <form method="POST" action="{{ route('admin.loans.changes.store', $loan) }}" class="flex flex-col gap-3" data-validate>
                        @csrf
                        <x-form.select name="type" id="change-type" :label="__('payroll.loans.fields.type')" required data-switch>
                            @foreach ($changeTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </x-form.select>
                        <div data-show-for="skip">
                            <x-form.select name="instalment_id" :label="__('payroll.loans.fields.instalment_id')">
                                @foreach ($scheduled as $instalment)
                                    <option value="{{ $instalment->id }}">#{{ $instalment->sequence }} · {{ $instalment->due_date->toDateString() }} · {{ App\Support\Money::format($instalment->amount) }}</option>
                                @endforeach
                            </x-form.select>
                        </div>
                        <div data-show-for="reschedule" class="flex flex-col gap-3">
                            <x-form.input type="number" name="new_instalment_count" min="1" max="120" :label="__('payroll.loans.fields.new_instalment_count')" />
                            <x-form.input type="month" name="new_start_month" :label="__('payroll.loans.fields.new_start_month')" :value="now()->addMonthNoOverflow()->format('Y-m')" />
                        </div>
                        <x-form.input name="reason" id="change-reason" required maxlength="1000" :label="__('payroll.fields.reason')" />
                        <x-button variant="secondary" icon="refresh">{{ __('payroll.loans.request_change') }}</x-button>
                    </form>
                </x-card>

                <x-card :title="__('payroll.loans.settle')" :subtitle="__('payroll.loans.settle_help')" data-region="settle">
                    <form method="POST" action="{{ route('admin.loans.settle', $loan) }}" class="flex flex-col gap-3" data-validate data-confirm="{{ __('payroll.loans.confirm_settle') }}" data-confirm-tone="warning">
                        @csrf
                        <x-form.input type="date" name="last_working_day" required :label="__('payroll.loans.fields.last_working_day')" :value="old('last_working_day', $lastWorkingDay)" />
                        <x-button variant="secondary" icon="log-out">{{ __('payroll.loans.settle') }}</x-button>
                    </form>
                </x-card>
            @endif

            <x-card :title="__('payroll.common.approval_history')">
                @include('admin.payroll._approval-history', ['requests' => $loan->approvalRequests])
            </x-card>
        </div>
    </div>
@endsection
