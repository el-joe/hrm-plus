@extends('layouts.admin', ['subtitle' => __('payroll_runs.payslips.subtitle')])

@section('title', __('payroll_runs.payslips.title'))

@section('page-actions')
    @can('export', App\Models\Payslip::class)
        <x-button variant="ghost" icon="download" :href="route('admin.payslips.export', request()->query())">{{ __('payroll.common.export_xlsx') }}</x-button>
    @endcan
@endsection

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

    <div class="grid gap-3 sm:grid-cols-2">
        <x-stat-card :label="__('payroll_runs.payslips.count')" :value="$payslips->total()" icon="file-text" />
        <x-stat-card :label="__('payroll_runs.payslips.outstanding')" :value="App\Support\Money::format($outstanding)" icon="wallet" :hint="$currency" />
    </div>

    <x-filter-bar :action="route('admin.payslips.index')" :reset="route('admin.payslips.index')">
        @if (! empty($filters['run']))
            <input type="hidden" name="run" value="{{ $filters['run'] }}">
        @endif
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="period" :label="__('payroll_runs.payslips.fields.period')" hide-label class="md:w-52">
            <option value="">{{ __('payroll_runs.payslips.fields.period') }}: {{ __('app.common.all') }}</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}" @selected((int) ($filters['period'] ?? 0) === $period->id)>{{ $period->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="payment_status" :label="__('payroll_runs.payslips.fields.payment_status')" hide-label class="md:w-48">
            <option value="">{{ __('payroll_runs.payslips.fields.payment_status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['payment_status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <form method="POST" action="{{ route('admin.payslips.mark-paid') }}" id="mark-paid-form" class="flex flex-col gap-4" data-validate>
        @csrf
        @if ($canPay)
            <x-card :title="__('payroll_runs.payslips.mark_paid')" :subtitle="__('payroll_runs.payslips.mark_paid_help')">
                <div class="grid gap-3 md:grid-cols-4 md:items-end">
                    <x-form.input type="date" name="paid_on" id="bulk-paid-on" required :label="__('payroll_runs.payslips.fields.paid_on')" :value="old('paid_on', now()->toDateString())" :max="now()->toDateString()" />
                    <x-form.select name="method" id="bulk-method" required :label="__('payroll_runs.payslips.fields.method')">
                        @foreach ($methods as $method)
                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                        @endforeach
                    </x-form.select>
                    <x-form.input name="reference" id="bulk-reference" maxlength="100" dir="ltr" :label="__('payroll_runs.payslips.fields.reference')" />
                    <x-button icon="check" data-confirm="{{ __('payroll_runs.payslips.confirm_mark_paid') }}">{{ __('payroll_runs.payslips.mark_selected_paid') }}</x-button>
                </div>
            </x-card>
        @endif

        <x-table :caption="__('payroll_runs.payslips.title')" data-region="payslips">
            <x-slot:head>
                <tr>
                    @if ($canPay)
                        <x-table.th class="w-10"><input type="checkbox" class="size-4" data-check-all="payslips" aria-label="{{ __('payroll_runs.payslips.select_all') }}"></x-table.th>
                    @endif
                    <x-table.th>{{ __('payroll_runs.payslips.fields.number') }}</x-table.th>
                    <x-table.th class="min-w-48">{{ __('payroll_runs.payslips.fields.employee') }}</x-table.th>
                    <x-table.th>{{ __('payroll_runs.payslips.fields.period') }}</x-table.th>
                    <x-table.th>{{ __('payroll_runs.payslips.fields.net') }}</x-table.th>
                    <x-table.th>{{ __('payroll_runs.payslips.fields.payment_status') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($payslips as $payslip)
                <x-table.row>
                    @if ($canPay)
                        <x-table.td :label="__('payroll_runs.payslips.select')">
                            @if (! $payslip->isVoid() && bccomp($payslip->outstanding(), '0', 2) > 0)
                                <input type="checkbox" name="payslip_ids[]" value="{{ $payslip->id }}" class="size-4" data-check-group="payslips" aria-label="{{ $payslip->number }}">
                            @endif
                        </x-table.td>
                    @endif
                    <x-table.td :label="__('payroll_runs.payslips.fields.number')" class="whitespace-nowrap">
                        <a href="{{ route('admin.payslips.show', $payslip) }}" class="text-label-md text-text-link hover:underline" dir="ltr">{{ $payslip->number }}</a>
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $payslip->run->reference }}</span>
                    </x-table.td>
                    <x-table.td :label="__('payroll_runs.payslips.fields.employee')">{{ $payslip->employee->label() }}</x-table.td>
                    <x-table.td :label="__('payroll_runs.payslips.fields.period')" class="whitespace-nowrap">{{ $payslip->period->name }}</x-table.td>
                    <x-table.td :label="__('payroll_runs.payslips.fields.net')">@include('admin.payroll._money', ['amount' => $payslip->net_pay, 'currency' => null])</x-table.td>
                    <x-table.td :label="__('payroll_runs.payslips.fields.payment_status')">
                        @if ($payslip->isVoid())
                            <x-badge tone="danger">{{ $payslip->status->label() }}</x-badge>
                        @else
                            <x-badge :tone="$payslip->payment_status->tone()">{{ $payslip->payment_status->label() }}</x-badge>
                            @if ($payslip->paid_on) <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $payslip->paid_on->toDateString() }}</span> @endif
                        @endif
                    </x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="$canPay ? 6 : 5" icon="file-text" :title="__('payroll_runs.payslips.empty')" />
            @endforelse
            <x-slot:footer>
                <x-pagination :paginator="$payslips" />
            </x-slot:footer>
        </x-table>
    </form>
@endsection
