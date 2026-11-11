{{-- Payslip PDF (mPDF). Plain tables and inline CSS: mPDF understands a subset of CSS. Variable set: PayslipDocument::data(). --}}
@php
    $t = fn (string $key, array $params = []) => __('payroll_runs.payslip_doc.'.$key, $params, $locale);
    $money = fn (?string $amount) => App\Support\Money::format($amount);
    $align = $dir === 'rtl' ? 'right' : 'left';
    $opposite = $dir === 'rtl' ? 'left' : 'right';
@endphp
<html dir="{{ $dir }}" lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: cairo; font-size: 10pt; color: #1f2937; }
        .muted { color: #6b7280; font-size: 8.5pt; }
        h1 { font-size: 16pt; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 4px 6px; vertical-align: top; }
        .lines th { background: #eef2f7; padding: 6px; font-weight: bold; text-align: {{ $align }}; }
        .lines td { padding: 5px 6px; border-bottom: 0.5pt solid #e5e7eb; }
        .amount { text-align: {{ $opposite }}; direction: ltr; white-space: nowrap; }
        .total td { font-weight: bold; border-top: 1pt solid #9ca3af; }
        .net { background: #0f3d6e; color: #ffffff; padding: 10px; font-size: 13pt; font-weight: bold; }
        .badge { color: #9a3412; background: #ffedd5; padding: 4px 6px; }
        .void { color: #991b1b; background: #fee2e2; padding: 6px; font-weight: bold; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 60%; text-align: {{ $align }};">
                @if ($logo_path)
                    <img src="{{ $logo_path }}" style="height: 40px;"><br>
                @endif
                <h1>{{ $company['name'] }}</h1>
                @if ($company['address'])<div class="muted">{{ $company['address'] }}</div>@endif
                @if ($company['tax_number'])<div class="muted">{{ $t('tax_number') }}: {{ $company['tax_number'] }}</div>@endif
            </td>
            <td style="width: 40%; text-align: {{ $opposite }};">
                <h1>{{ $t('title') }}</h1>
                <div>{{ $period['name'] }}</div>
                <div class="muted" dir="ltr">{{ $payslip->number }}</div>
            </td>
        </tr>
    </table>
    <br>
    @if ($void)<div class="void">{{ $t('void') }}</div><br>@endif
    @if ($demo)<div class="badge">{{ $t('demo') }}</div><br>@endif

    <table class="info" style="border: 0.5pt solid #e5e7eb;">
        <tr>
            <td><span class="muted">{{ $t('employee') }}</span><br><b>{{ $employee['name'] }}</b></td>
            <td><span class="muted">{{ $t('number') }}</span><br><b dir="ltr">{{ $employee['number'] }}</b></td>
            <td><span class="muted">{{ $t('department') }}</span><br>{{ $employee['department'] ?? '—' }}</td>
        </tr>
        <tr>
            <td><span class="muted">{{ $t('job_title') }}</span><br>{{ $employee['job_title'] ?? '—' }}</td>
            <td><span class="muted">{{ $t('branch') }}</span><br>{{ $employee['branch'] ?? '—' }}</td>
            <td><span class="muted">{{ $t('period') }}</span><br>{!! str_replace(['%FROM%', '%TO%'], ['<bdo dir="ltr">'.e($period['from']).'</bdo>', '<bdo dir="ltr">'.e($period['to']).'</bdo>'], e($t('range', ['from' => '%FROM%', 'to' => '%TO%']))) !!}</td>
        </tr>
        <tr>
            <td><span class="muted">{{ $t('basic_salary') }}</span><br><bdo dir="ltr">{{ $basic_salary === null ? '—' : $money($basic_salary).' '.$currency }}</bdo></td>
            <td><span class="muted">{{ $t('pay_date') }}</span><br><bdo dir="ltr">{{ $period['pay_date'] }}</bdo></td>
            <td><span class="muted">{{ $t('payment_status') }}</span><br>{{ $void ? $t('void_short') : __('payroll_runs.enums.payment_statuses.'.$payslip->payment_status->value, [], $locale) }}@if ($payslip->paid_on) · <span dir="ltr">{{ $payslip->paid_on->toDateString() }}</span>@endif</td>
        </tr>
    </table>
    <br>

    @foreach (['earnings' => $earnings, 'deductions' => $deductions, 'statutory' => $statutory, 'loans' => $loans] as $group => $rows)
        @if ($group === 'earnings' || $rows !== [])
            <table class="lines">
                <tr><th>{{ $t($group) }}</th><th class="amount" style="text-align: {{ $opposite }};">{{ $t('amount') }} ({{ $currency }})</th></tr>
                @foreach ($rows as $row)
                    <tr><td>{{ $row['name'] }}@if ($row['quantity'] !== null && $row['code'] !== 'BASIC') <span class="muted">({{ $row['quantity'] }})</span>@endif</td><td class="amount">{{ $money($row['amount']) }}</td></tr>
                @endforeach
                @if ($group === 'earnings')
                    <tr class="total"><td>{{ $t('gross') }}</td><td class="amount">{{ $money($gross) }}</td></tr>
                @endif
            </table>
            <br>
        @endif
    @endforeach

    <table class="lines">
        <tr class="total"><td>{{ $t('total_deductions') }}</td><td class="amount">{{ $money($deductions_total) }}</td></tr>
    </table>
    <br>
    <table>
        <tr><td class="net">{{ $t('net') }}</td><td class="net amount">{{ $money($net) }} {{ $currency }}</td></tr>
    </table>
    <br>
    <div class="muted">{!! str_replace(['%DATE%', '%RUN%'], ['<bdo dir="ltr">'.e($payslip->issued_at->format('Y-m-d')).'</bdo>', '<bdo dir="ltr">'.e($run['reference']).'</bdo>'], e($t('footer', ['date' => '%DATE%', 'run' => '%RUN%']))) !!}</div>
</body>
</html>
