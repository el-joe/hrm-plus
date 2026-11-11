@extends('layouts.employee')

@section('title', __('payroll_runs.portal.title'))

@section('content')
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('payroll_runs.portal.year_net', ['year' => $year])" :value="App\Support\Money::format($summary['net'])" icon="wallet" :hint="$currency" />
        <x-stat-card :label="__('payroll_runs.portal.year_gross', ['year' => $year])" :value="App\Support\Money::format($summary['gross'])" icon="trend-up" :hint="$currency" />
        <x-stat-card :label="__('payroll_runs.portal.year_deductions', ['year' => $year])" :value="App\Support\Money::format($summary['deductions'])" icon="minus-circle" :hint="$currency" />
        <x-stat-card :label="__('payroll_runs.portal.loan_balance')" :value="App\Support\Money::format($loanBalance)" icon="coins" :hint="$currency" :href="route('portal.loans')" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('payroll_runs.portal.payslips')" padding="none" class="lg:col-span-2" data-region="my-payslips">
            @if (count($years) > 1)
                <x-slot:actions>
                    <form method="GET" action="{{ route('portal.payroll') }}" data-filters>
                        <x-form.select name="year" :label="__('payroll_runs.portal.year')" hide-label class="w-32">
                            @foreach ($years as $option)
                                <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
                            @endforeach
                        </x-form.select>
                        <noscript><x-button size="sm" variant="secondary">{{ __('app.actions.filter') }}</x-button></noscript>
                    </form>
                </x-slot:actions>
            @endif
            <ul class="divide-y divide-border-subtle">
                @forelse ($payslips as $payslip)
                    <li class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <a href="{{ route('portal.payroll.show', $payslip->id) }}" class="text-label-md text-text-link hover:underline">{{ $payslip->period->name }}</a>
                            <p class="text-caption-md text-text-tertiary"><span dir="ltr">{{ $payslip->number }}</span> · {{ $payslip->run->type->label() }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            @include('admin.payroll._money', ['amount' => $payslip->net_pay, 'currency' => $currency])
                            <x-badge :tone="$payslip->payment_status->tone()">{{ $payslip->payment_status->label() }}</x-badge>
                            <x-icon-button icon="download" :label="__('payroll_runs.payslips.download_pdf')" :href="route('portal.payroll.pdf', [$payslip->id, 'download' => 1])" />
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-6"><x-empty-state icon="file-text" :title="__('payroll_runs.portal.no_payslips')" :description="__('payroll_runs.portal.no_payslips_help')" /></li>
                @endforelse
            </ul>
            <x-slot:footer>
                <x-pagination :paginator="$payslips" />
            </x-slot:footer>
        </x-card>

        <div class="flex flex-col gap-4 lg:gap-6">
            @if ($structure)
                <x-card :title="__('payroll_runs.portal.structure')" :subtitle="$structure['structure'] ?? __('payroll.common.none')" data-region="my-structure">
                    <dl class="flex flex-col gap-2 text-body-sm">
                        <div class="flex justify-between gap-3"><dt class="text-text-secondary">{{ __('payroll.common.basic_salary') }}</dt><dd>@include('admin.payroll._money', ['amount' => $structure['basic'], 'currency' => $currency])</dd></div>
                        @if ($structure['pay_basis'])
                            <div class="flex justify-between gap-3"><dt class="text-text-secondary">{{ __('payroll_runs.items.pay_basis') }}</dt><dd>{{ $structure['pay_basis'] }}</dd></div>
                        @endif
                        @foreach ([...$structure['lines'], ...$structure['allowances']] as $line)
                            <div class="flex justify-between gap-3">
                                <dt class="text-text-secondary">{{ $line['name'] }}</dt>
                                <dd class="text-end"><span dir="ltr">{{ $line['rule'] }}</span> <span class="text-caption-md text-text-tertiary">· {{ __('payroll.enums.kinds.'.$line['kind']) }}</span></dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="mt-3 text-caption-md text-text-tertiary">{{ __('payroll_runs.portal.structure_help') }}</p>
                </x-card>
            @endif
            <x-card :title="__('payroll_runs.portal.year_summary', ['year' => $year])">
                <dl class="flex flex-col gap-2 text-body-sm">
                    <div class="flex justify-between"><dt class="text-text-secondary">{{ __('payroll_runs.portal.payslip_count') }}</dt><dd class="tabular-nums">{{ $summary['count'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-text-secondary">{{ __('payroll_runs.runs.fields.gross') }}</dt><dd>@include('admin.payroll._money', ['amount' => $summary['gross']])</dd></div>
                    <div class="flex justify-between"><dt class="text-text-secondary">{{ __('payroll_runs.runs.fields.deductions') }}</dt><dd>@include('admin.payroll._money', ['amount' => $summary['deductions']])</dd></div>
                    <div class="flex justify-between font-semibold"><dt>{{ __('payroll_runs.runs.fields.net') }}</dt><dd>@include('admin.payroll._money', ['amount' => $summary['net']])</dd></div>
                    <div class="flex justify-between"><dt class="text-text-secondary">{{ __('payroll_runs.portal.paid') }}</dt><dd>@include('admin.payroll._money', ['amount' => $summary['paid']])</dd></div>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
