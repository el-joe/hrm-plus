@extends('layouts.admin', [
    'breadcrumbs' => [
        ['label' => __('payroll_runs.runs.title'), 'url' => route('admin.payroll-runs.index')],
        ['label' => $run->reference, 'url' => route('admin.payroll-runs.show', $run)],
    ],
    'subtitle' => $run->period->name.' · '.$item->employee->employee_number,
])

@section('title', __('payroll_runs.items.title', ['name' => $item->employee->name]))

@section('page-actions')
    @if ($prevItem)
        <x-button variant="ghost" icon="chevron-left" :href="route('admin.payroll-runs.items.show', [$run, $prevItem])">{{ __('payroll_runs.items.previous') }}</x-button>
    @endif
    @if ($nextItem)
        <x-button variant="ghost" icon-end="chevron-right" :href="route('admin.payroll-runs.items.show', [$run, $nextItem])">{{ __('payroll_runs.items.next') }}</x-button>
    @endif
    @if ($item->payslip)
        @can('view', $item->payslip)
            <x-button variant="secondary" icon="file-text" :href="route('admin.payslips.show', $item->payslip)">{{ __('payroll_runs.items.payslip') }}</x-button>
        @endcan
    @endif
@endsection

@section('content')
    @php
        $currency = $item->currency;
        $quantities = $item->quantities ?? [];
        $attendance = $quantities['attendance'] ?? null;
        $totals = $quantities['totals'] ?? null;
    @endphp

    @if ($item->error)
        <x-alert tone="danger" :title="__('payroll_runs.items.failed')">{{ $item->error }}</x-alert>
    @endif
    @if (! empty($item->warnings))
        <x-alert :tone="$item->has_blocking_warning ? 'danger' : 'warning'" :title="__('payroll_runs.items.warnings')">
            @include('admin.payroll.runs._warnings', ['warnings' => $item->warnings, 'full' => true])
        </x-alert>
    @endif
    @if ($pendingAdjustments > 0 && $run->isEditable())
        <x-alert tone="info">{{ __('payroll_runs.items.pending_adjustments', ['count' => $pendingAdjustments]) }}</x-alert>
    @endif

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('payroll_runs.runs.fields.gross')" :value="App\Support\Money::format($item->gross_pay)" icon="trend-up" :hint="$currency" />
        <x-stat-card :label="__('payroll_runs.runs.fields.deductions')" :value="App\Support\Money::format($item->total_deductions)" icon="minus-circle" :hint="$currency" />
        <x-stat-card :label="__('payroll_runs.runs.fields.net')" :value="App\Support\Money::format($item->net_pay)" icon="wallet" :hint="$currency"
            :trend="$variance === null ? null : (bccomp($variance, '0', 1) >= 0 ? 'up' : 'down')" :trend-value="$variance === null ? null : $variance.'%'" />
        <x-stat-card :label="__('payroll_runs.runs.fields.employer')" :value="App\Support\Money::format($item->employer_contributions)" icon="building" :hint="__('payroll_runs.runs.employer_hint')" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex min-w-0 flex-col gap-4 lg:col-span-2 lg:gap-6">
            @include('admin.payroll.items._lines', ['lines' => $earnings, 'title' => __('payroll_runs.items.earnings'), 'total' => $item->gross_pay, 'currency' => $currency])
            @include('admin.payroll.items._lines', ['lines' => $deductions, 'title' => __('payroll_runs.items.deductions'), 'total' => $item->total_deductions, 'currency' => $currency])
            @if ($employer->isNotEmpty())
                @include('admin.payroll.items._lines', ['lines' => $employer, 'title' => __('payroll_runs.items.employer'), 'total' => $item->employer_contributions, 'currency' => $currency])
            @endif

            <x-card :title="__('payroll_runs.items.net_pay')">
                <p class="text-body-md text-text-secondary">{{ __('payroll_runs.items.net_formula', ['gross' => App\Support\Money::format($item->gross_pay), 'deductions' => App\Support\Money::format($item->total_deductions), 'net' => App\Support\Money::format($item->net_pay)]) }}</p>
                @if ($previous)
                    <p class="mt-1 text-caption-md text-text-tertiary">{{ __('payroll_runs.items.previous_net', ['period' => $previous['period'], 'net' => App\Support\Money::format($previous['net'])]) }}</p>
                @endif
            </x-card>

            <x-card :title="__('payroll_runs.items.inputs')" :subtitle="__('payroll_runs.items.inputs_help')">
                <details>
                    <summary class="cursor-pointer text-label-md text-text-link">{{ __('payroll_runs.items.show_snapshot') }}</summary>
                    <pre class="mt-3 max-h-96 overflow-auto rounded-md bg-surface-subtle p-3 text-caption-md text-text-secondary" dir="ltr">{{ json_encode(['inputs' => $item->inputs, 'quantities' => $item->quantities, 'rule_versions' => $item->rule_versions], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </details>
                <p class="mt-3 text-caption-md text-text-tertiary">{{ __('payroll_runs.items.hash') }} <code class="break-all" dir="ltr">{{ $item->calculation_hash }}</code></p>
            </x-card>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('payroll_runs.items.employee')">
                <x-detail-list>
                    <x-detail-item :label="__('payroll_runs.preview.employee')">
                        @can('view', $item->employee)
                            <a class="text-text-link hover:underline" href="{{ route('admin.employees.show', $item->employee) }}">{{ $item->employee->label() }}</a>
                        @else
                            {{ $item->employee->label() }}
                        @endcan
                    </x-detail-item>
                    <x-detail-item :label="__('payroll_runs.preview.branch')">{{ $item->branch?->name }} · {{ $item->department?->name }}</x-detail-item>
                    <x-detail-item :label="__('payroll_runs.items.pay_basis')">{{ $item->pay_basis?->label() ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('payroll.common.basic_salary')">@include('admin.payroll._money', ['amount' => $item->basic_salary, 'currency' => $currency])</x-detail-item>
                    <x-detail-item :label="__('payroll_runs.items.proration')"><span dir="ltr" class="tabular-nums">{{ App\Services\Payroll\PayrollMath::trim((string) $item->proration_factor) }}</span></x-detail-item>
                    <x-detail-item :label="__('payroll_runs.items.taxable_base')">@include('admin.payroll._money', ['amount' => $item->taxable_base, 'currency' => null])</x-detail-item>
                    <x-detail-item :label="__('payroll_runs.items.insurable_base')">@include('admin.payroll._money', ['amount' => $item->insurable_base, 'currency' => null])</x-detail-item>
                </x-detail-list>
            </x-card>

            @if (count($quantities['placement'] ?? []) > 0)
                <x-card :title="__('payroll_runs.items.placement')">
                    <ol class="flex flex-col gap-2">
                        @foreach ($quantities['placement'] as $segment)
                            <li class="text-body-sm text-text-secondary"><span dir="ltr">{{ $segment['from'] }} → {{ $segment['to'] }}</span> · {{ $branchNames->get($segment['branch_id'])?->name ?? '—' }} · {{ $departmentNames->get($segment['department_id'])?->name ?? '—' }}</li>
                        @endforeach
                    </ol>
                    @if ($item->moved_in_period)
                        <p class="mt-2 text-caption-md text-text-tertiary">{{ __('payroll_runs.items.moved_help') }}</p>
                    @endif
                </x-card>
            @endif

            @if ($attendance)
                <x-card :title="__('payroll_runs.items.attendance')">
                    <x-detail-list>
                        <x-detail-item :label="__('payroll_runs.items.q.days')">{{ $attendance['days'] }}</x-detail-item>
                        <x-detail-item :label="__('payroll_runs.items.q.attended_days')">{{ App\Services\Payroll\PayrollMath::trim($attendance['attended_days']) }}</x-detail-item>
                        <x-detail-item :label="__('payroll_runs.items.q.absent_days')">{{ App\Services\Payroll\PayrollMath::trim($attendance['absent_days']) }}</x-detail-item>
                        <x-detail-item :label="__('payroll_runs.items.q.late_minutes')">{{ App\Services\Payroll\PayrollMath::trim($attendance['late_minutes']) }}</x-detail-item>
                        <x-detail-item :label="__('payroll_runs.items.q.early_leave_minutes')">{{ App\Services\Payroll\PayrollMath::trim($attendance['early_leave_minutes']) }}</x-detail-item>
                        <x-detail-item :label="__('payroll_runs.items.q.leave_days')">{{ App\Services\Payroll\PayrollMath::trim($attendance['leave_days']) }}</x-detail-item>
                        <x-detail-item :label="__('payroll_runs.items.q.unpaid_leave_days')">{{ App\Services\Payroll\PayrollMath::trim($totals['unpaid_leave_days'] ?? '0') }}</x-detail-item>
                        @foreach ($attendance['overtime'] as $row)
                            <x-detail-item :label="__('payroll_runs.items.q.overtime', ['kind' => __('payroll_runs.overtime_kinds.'.$row['kind'])])"><span dir="ltr">{{ App\Services\Payroll\PayrollMath::trim($row['hours']) }} h × {{ App\Services\Payroll\PayrollMath::trim($row['multiplier']) }}</span></x-detail-item>
                        @endforeach
                    </x-detail-list>
                    @if (! empty($quantities['unpaid_leave']))
                        <p class="mt-3 text-label-md text-text-primary">{{ __('payroll_runs.items.unpaid_dates') }}</p>
                        <ul class="mt-1 flex flex-col gap-1 text-caption-md text-text-secondary">
                            @foreach ($quantities['unpaid_leave'] as $day)
                                <li><span dir="ltr">{{ $day['date'] }}</span> · {{ $day['leave_type'] }} · {{ App\Services\Payroll\PayrollMath::trim($day['amount']) }}</li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endif

            @can('update', $run)
                <x-card :title="__('payroll_runs.runs.remove_employee')" :subtitle="__('payroll_runs.items.remove_help')">
                    <form method="POST" action="{{ route('admin.payroll-runs.items.destroy', [$run, $item]) }}" class="flex flex-col gap-2" data-validate data-confirm="{{ __('payroll_runs.items.confirm_remove') }}" data-confirm-tone="warning">
                        @csrf
                        @method('DELETE')
                        <x-form.input name="reason" id="remove-reason" required maxlength="500" :label="__('payroll_runs.runs.remove_reason')" />
                        <x-button size="sm" variant="danger" icon="x">{{ __('payroll_runs.runs.remove_employee') }}</x-button>
                    </form>
                </x-card>
            @endcan

            <x-card :title="__('payroll_runs.items.rule_versions')">
                @php($versions = $item->rule_versions ?? [])
                <x-detail-list>
                    <x-detail-item :label="__('payroll_runs.items.structure')">
                        @if ($versions['structure'] ?? null)
                            <span dir="ltr">{{ $versions['structure']['code'] }}</span> · {{ __('payroll.common.version', ['version' => $versions['structure']['version']]) }} · {{ __('payroll.enums.structure_scopes.'.$versions['structure']['scope']) }}
                        @else
                            {{ __('payroll.common.none') }}
                        @endif
                    </x-detail-item>
                    @foreach ($versions['statutory'] ?? [] as $type => $rule)
                        <x-detail-item :label="__('payroll.enums.statutory_types.'.$type)">
                            <span dir="ltr">{{ $rule['set'] }}</span> · {{ __('payroll.common.version', ['version' => $rule['version']]) }}
                            @if ($rule['is_demo']) @include('admin.payroll._demo') @endif
                        </x-detail-item>
                    @endforeach
                    <x-detail-item :label="__('payroll_runs.items.entries')">{{ count($versions['entries'] ?? []) }}</x-detail-item>
                    <x-detail-item :label="__('payroll_runs.items.calculated_at')"><span dir="ltr">{{ $item->calculated_at?->format('Y-m-d H:i') ?? '—' }}</span></x-detail-item>
                </x-detail-list>
            </x-card>
        </div>
    </div>
@endsection
