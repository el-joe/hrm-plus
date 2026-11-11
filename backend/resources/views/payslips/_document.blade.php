{{--
    Payslip document (HTML) shared by the admin and the portal. Variable: $doc (App\Services\Payroll\PayslipDocument::data()).
    Rendered in the payslip's language (dir set on the wrapper), independent of the interface language.
--}}
@php($t = fn (string $key, array $params = []) => __('payroll_runs.payslip_doc.'.$key, $params, $doc['locale']))
<article dir="{{ $doc['dir'] }}" lang="{{ $doc['locale'] }}" class="flex flex-col gap-5 rounded-xl border border-border-subtle bg-surface-default p-4 shadow-xs sm:p-6" data-payslip>
    <header class="flex flex-col gap-3 border-b border-border-subtle pb-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-center gap-3">
            @if ($doc['logo_url'])
                <img src="{{ $doc['logo_url'] }}" alt="" class="h-12 w-auto max-w-32 object-contain">
            @endif
            <div>
                <p class="text-h4 text-text-primary">{{ $doc['company']['name'] }}</p>
                @if ($doc['company']['address'])
                    <p class="text-caption-md text-text-tertiary">{{ $doc['company']['address'] }}</p>
                @endif
            </div>
        </div>
        <div class="text-start sm:text-end">
            <p class="text-h4 text-text-primary">{{ $t('title') }}</p>
            <p class="text-body-sm text-text-secondary">{{ $doc['period']['name'] }}</p>
            <p class="text-caption-md text-text-tertiary"><span dir="ltr">{{ $doc['payslip']->number }}</span></p>
        </div>
    </header>

    @if ($doc['void'])
        <x-alert tone="danger">{{ $t('void') }}</x-alert>
    @endif
    @if ($doc['demo'])
        <x-alert tone="warning">{{ $t('demo') }}</x-alert>
    @endif

    <dl class="grid grid-cols-1 gap-3 text-body-sm sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            'employee' => $doc['employee']['name'], 'number' => $doc['employee']['number'], 'department' => $doc['employee']['department'],
            'job_title' => $doc['employee']['job_title'], 'branch' => $doc['employee']['branch'], 'pay_date' => $doc['period']['pay_date'],
            'period' => $t('range', ['from' => $doc['period']['from'], 'to' => $doc['period']['to']]), 'basic_salary' => $doc['basic_salary'] === null ? '—' : App\Support\Money::format($doc['basic_salary']).' '.$doc['currency'],
            'payment_status' => $doc['payslip']->isVoid() ? $doc['payslip']->status->label() : __('payroll_runs.enums.payment_statuses.'.$doc['payslip']->payment_status->value, [], $doc['locale']),
        ] as $key => $value)
            <div class="flex flex-col gap-0.5">
                <dt class="text-caption-md text-text-tertiary">{{ $t($key) }}</dt>
                <dd class="text-label-md text-text-primary">@if (in_array($key, ['number', 'pay_date', 'basic_salary'], true))<span dir="ltr">{{ $value ?? '—' }}</span>@else{{ $value ?? '—' }}@endif</dd>
            </div>
        @endforeach
    </dl>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach (['earnings' => $doc['earnings'], 'deductions' => [...$doc['deductions'], ...$doc['statutory'], ...$doc['loans']]] as $group => $rows)
            <section class="flex flex-col gap-2">
                <h3 class="text-label-lg text-text-primary">{{ $t($group) }}</h3>
                <table class="w-full text-body-sm">
                    <tbody class="divide-y divide-border-subtle">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="py-2 pe-3 text-text-secondary">{{ $row['name'] }}@if ($row['quantity'] !== null && ! in_array($row['code'], ['BASIC'], true)) <span class="text-caption-md text-text-tertiary">({{ $row['quantity'] }})</span>@endif</td>
                                <td class="py-2 text-end">@include('admin.payroll._money', ['amount' => $row['amount'], 'currency' => null])</td>
                            </tr>
                        @empty
                            <tr><td class="py-2 text-text-tertiary" colspan="2">—</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-border-default">
                            <td class="py-2 text-label-md text-text-primary">{{ $t($group === 'earnings' ? 'gross' : 'total_deductions') }}</td>
                            <td class="py-2 text-end text-label-md">@include('admin.payroll._money', ['amount' => $group === 'earnings' ? $doc['gross'] : $doc['deductions_total'], 'currency' => $doc['currency']])</td>
                        </tr>
                    </tfoot>
                </table>
            </section>
        @endforeach
    </div>

    <div class="flex flex-col items-start justify-between gap-2 rounded-lg bg-surface-subtle p-4 sm:flex-row sm:items-center">
        <span class="text-h4 text-text-primary">{{ $t('net') }}</span>
        <span class="text-numeric-lg text-text-primary">@include('admin.payroll._money', ['amount' => $doc['net'], 'currency' => $doc['currency']])</span>
    </div>
</article>
