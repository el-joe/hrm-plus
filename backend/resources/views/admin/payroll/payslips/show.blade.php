@extends('layouts.admin', ['breadcrumbs' => [['label' => __('payroll_runs.payslips.title'), 'url' => route('admin.payslips.index')]], 'subtitle' => $doc['employee']['name']])

@section('title', __('payroll_runs.payslips.show_title', ['number' => $payslip->number]))

@section('page-actions')
    <x-button variant="ghost" icon="globe" :href="route('admin.payslips.show', [$payslip, 'lang' => $doc['locale'] === 'ar' ? 'en' : 'ar'])">{{ $doc['locale'] === 'ar' ? 'English' : 'العربية' }}</x-button>
    <x-button variant="secondary" icon="eye" :href="route('admin.payslips.pdf', [$payslip, 'lang' => $doc['locale']])" target="_blank" rel="noopener">{{ __('payroll_runs.payslips.view_pdf') }}</x-button>
    <x-button icon="download" :href="route('admin.payslips.pdf', [$payslip, 'lang' => $doc['locale'], 'download' => 1])">{{ __('payroll_runs.payslips.download_pdf') }}</x-button>
@endsection

@section('content')
    @if ($errors->any())
        <x-alert tone="danger">{{ $errors->first() }}</x-alert>
    @endif
    <p class="text-caption-md text-text-tertiary">{{ __('payroll_runs.payslips.access_logged') }}</p>

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="min-w-0 lg:col-span-2">
            @include('payslips._document')
        </div>
        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('payroll_runs.payslips.payments')" data-region="payments">
                <ul class="flex flex-col gap-2">
                    @forelse ($payslip->payments as $payment)
                        <li class="text-body-sm text-text-secondary">
                            @include('admin.payroll._money', ['amount' => $payment->amount, 'currency' => $payslip->currency]) · <span dir="ltr">{{ $payment->paid_on->toDateString() }}</span> · {{ $payment->method->label() }}
                            @if ($payment->reference) · <span dir="ltr">{{ $payment->reference }}</span>@endif
                            <span class="block text-caption-md text-text-tertiary">{{ $payment->recorder?->name }}</span>
                        </li>
                    @empty
                        <li class="text-body-sm text-text-tertiary">{{ __('payroll_runs.payslips.no_payments') }}</li>
                    @endforelse
                </ul>
                @if ($canPay && bccomp($payslip->outstanding(), '0', 2) > 0)
                    <form method="POST" action="{{ route('admin.payslips.payments.store', $payslip) }}" class="mt-4 flex flex-col gap-3" data-validate>
                        @csrf
                        <x-form.input type="number" name="amount" step="0.01" min="0.01" :max="$payslip->outstanding()" dir="ltr" required :label="__('payroll_runs.payslips.fields.amount')" :value="old('amount', $payslip->outstanding())" />
                        <x-form.input type="date" name="paid_on" required :label="__('payroll_runs.payslips.fields.paid_on')" :value="old('paid_on', now()->toDateString())" :max="now()->toDateString()" />
                        <x-form.select name="method" required :label="__('payroll_runs.payslips.fields.method')">
                            @foreach ($methods as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.input name="reference" maxlength="100" dir="ltr" :label="__('payroll_runs.payslips.fields.reference')" />
                        <x-button icon="check">{{ __('payroll_runs.payslips.record_payment') }}</x-button>
                    </form>
                @endif
            </x-card>
            <x-card :title="__('payroll_runs.payslips.run')">
                <a class="text-text-link hover:underline" href="{{ route('admin.payroll-runs.show', $payslip->payroll_run_id) }}" dir="ltr">{{ $doc['run']['reference'] }}</a>
                @can('viewItem', [$payslip->run, $payslip->item])
                    · <a class="text-text-link hover:underline" href="{{ route('admin.payroll-runs.items.show', [$payslip->payroll_run_id, $payslip->payroll_item_id]) }}">{{ __('payroll_runs.payslips.breakdown') }}</a>
                @endcan
            </x-card>
        </div>
    </div>
@endsection
