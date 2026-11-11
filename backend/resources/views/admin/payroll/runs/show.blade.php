@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('payroll_runs.runs.title'), 'url' => route('admin.payroll-runs.index')]],
    'subtitle' => $run->period->name.' · '.$run->type->label().' · '.($run->scope_summary ?? ''),
])

@section('title', $run->reference.' · '.$run->name)

@section('page-actions')
    @include('admin.payroll.runs._status')
    @if ($can['export'])
        <x-dropdown :label="__('payroll_runs.runs.export')">
            <x-slot:trigger>
                <span class="inline-flex h-10 items-center gap-2 rounded-md border border-border-default bg-surface-default px-4 text-label-md text-text-primary hover:bg-surface-subtle">
                    <x-icon name="download" size="sm" /> {{ __('payroll_runs.runs.export') }} <x-icon name="chevron-down" size="sm" />
                </span>
            </x-slot:trigger>
            <x-dropdown.item :href="route('admin.payroll-runs.export', [$run, 'register'])">{{ __('payroll_runs.runs.export_register_xlsx') }}</x-dropdown.item>
            <x-dropdown.item :href="route('admin.payroll-runs.export', [$run, 'register', 'format' => 'csv'])">{{ __('payroll_runs.runs.export_register_csv') }}</x-dropdown.item>
            @if (in_array($run->status->value, ['approved', 'finalised'], true))
                <x-dropdown.divider />
                <x-dropdown.item :href="route('admin.payroll-runs.export', [$run, 'bank'])">{{ __('payroll_runs.runs.export_bank_xlsx') }}</x-dropdown.item>
                <x-dropdown.item :href="route('admin.payroll-runs.export', [$run, 'bank', 'format' => 'csv'])">{{ __('payroll_runs.runs.export_bank_csv') }}</x-dropdown.item>
            @endif
        </x-dropdown>
    @endif
@endsection

@section('content')
    @php
        $currency = $run->currency;
        $step = match ($run->status->value) { 'draft', 'calculating', 'calculated' => 3, 'pending_approval' => 4, 'approved' => 5, default => 6 };
        $hasDemo = $items->contains(fn ($item) => $item->hasWarning('demo_statutory'));
    @endphp

    @if ($errors->any())
        <x-alert tone="danger">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    @if (in_array($run->type->value, ['regular', 'correction', 'final_settlement'], true) && $run->status->isLive())
        @include('admin.payroll.runs._wizard', ['current' => $step])
    @endif

    @if ($run->status->value === 'reversed')
        <x-alert tone="danger" :title="__('payroll_runs.runs.reversed_title')">
            {{ __('payroll_runs.runs.reversed_by', ['name' => $run->reverser?->name ?? '—', 'date' => $run->reversed_at?->format('Y-m-d H:i'), 'reason' => $run->reversal_reason]) }}
            @if ($run->reversal)
                <a class="text-text-link hover:underline" href="{{ route('admin.payroll-runs.show', $run->reversal) }}" dir="ltr">{{ $run->reversal->reference }}</a>
            @endif
        </x-alert>
    @endif
    @if ($run->correctsRun)
        <x-alert tone="info">{{ __('payroll_runs.runs.mirror_of') }} <a class="text-text-link hover:underline" href="{{ route('admin.payroll-runs.show', $run->correctsRun) }}" dir="ltr">{{ $run->correctsRun->reference }}</a></x-alert>
    @endif
    @if ($run->offboardingCase)
        <x-alert tone="info">{{ __('payroll_runs.final_settlement.linked_case') }} <a class="text-text-link hover:underline" href="{{ route('admin.offboarding.show', $run->offboardingCase) }}">#{{ $run->offboardingCase->id }}</a></x-alert>
    @endif
    @if ($hasDemo)
        <x-alert tone="warning" :title="__('payroll.common.demo_badge')">{{ __('payroll_runs.runs.demo_help') }}</x-alert>
    @endif
    @if ($run->isEditable() && $run->period->cutoff_date?->isFuture())
        <x-alert tone="warning">{{ __('payroll_runs.runs.cutoff_not_reached', ['date' => $run->period->cutoff_date->toDateString()]) }}</x-alert>
    @endif
    @if ($run->calculation_error)
        <x-alert tone="danger" :title="__('payroll_runs.runs.calculation_failed')">{{ $run->calculation_error }}</x-alert>
    @endif

    @if ($run->status->value === 'calculating')
        <x-card :title="__('payroll_runs.runs.calculating')" data-payroll-progress="{{ route('admin.payroll-runs.progress', $run) }}">
            <div class="h-2 w-full overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $run->progressPercent() }}" data-progress-bar>
                <div class="h-full bg-action-primary-default" style="inline-size: {{ $run->progressPercent() }}%" data-progress-fill></div>
            </div>
            <p class="mt-2 text-caption-md text-text-tertiary" data-progress-text>{{ __('payroll_runs.runs.progress', ['done' => $run->progress_done, 'total' => $run->progress_total]) }}</p>
            <noscript><p class="text-caption-md"><a class="text-text-link" href="{{ route('admin.payroll-runs.show', $run) }}">{{ __('payroll_runs.runs.reload') }}</a></p></noscript>
        </x-card>
    @endif

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <x-stat-card :label="__('payroll_runs.runs.fields.employees')" :value="$totals['employees']" icon="users" />
        <x-stat-card :label="__('payroll_runs.runs.fields.gross')" :value="App\Support\Money::format($totals['gross'])" icon="trend-up" :hint="$currency" />
        <x-stat-card :label="__('payroll_runs.runs.fields.deductions')" :value="App\Support\Money::format($totals['deductions'])" icon="minus-circle" :hint="$currency" />
        <x-stat-card :label="__('payroll_runs.runs.fields.net')" :value="App\Support\Money::format($totals['net'])" icon="wallet" :hint="$currency" />
        <x-stat-card :label="__('payroll_runs.runs.fields.employer')" :value="App\Support\Money::format($totals['employer'])" icon="building" :hint="__('payroll_runs.runs.employer_hint')" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex min-w-0 flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-tabs :label="__('payroll_runs.runs.sections')" :items="[
                ['label' => __('payroll_runs.runs.tabs.employees'), 'url' => route('admin.payroll-runs.show', [$run, 'tab' => 'employees']), 'active' => $tab === 'employees'],
                ['label' => __('payroll_runs.runs.tabs.components'), 'url' => route('admin.payroll-runs.show', [$run, 'tab' => 'components']), 'active' => $tab === 'components'],
                ['label' => __('payroll_runs.runs.tabs.approvals'), 'url' => route('admin.payroll-runs.show', [$run, 'tab' => 'approvals']), 'active' => $tab === 'approvals'],
                ['label' => __('payroll_runs.runs.tabs.history'), 'url' => route('admin.payroll-runs.show', [$run, 'tab' => 'history']), 'active' => $tab === 'history'],
            ]" />

            @if ($tab === 'employees')
                <x-filter-bar :action="route('admin.payroll-runs.show', $run)" :reset="route('admin.payroll-runs.show', $run)">
                    <input type="hidden" name="tab" value="employees">
                    <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-48" data-filter-debounce />
                    <x-form.select name="branch" :label="__('payroll_runs.preview.branch')" hide-label class="md:w-40">
                        <option value="">{{ __('payroll_runs.preview.branch') }}: {{ __('app.common.all') }}</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) ($filters['branch'] ?? 0) === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </x-form.select>
                    <x-form.select name="department" :label="__('payroll_runs.preview.department')" hide-label class="md:w-44">
                        <option value="">{{ __('payroll_runs.preview.department') }}: {{ __('app.common.all') }}</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) ($filters['department'] ?? 0) === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </x-form.select>
                    <x-form.select name="flag" :label="__('payroll_runs.runs.flag')" hide-label class="md:w-48">
                        <option value="">{{ __('payroll_runs.runs.flag') }}: {{ __('app.common.all') }}</option>
                        @foreach (['warnings', 'blocking', 'variance', 'moved', 'failed'] as $flag)
                            <option value="{{ $flag }}" @selected(($filters['flag'] ?? '') === $flag)>{{ __('payroll_runs.runs.flags.'.$flag) }}</option>
                        @endforeach
                    </x-form.select>
                </x-filter-bar>

                <x-table :caption="__('payroll_runs.runs.tabs.employees')" data-region="run-items">
                    <x-slot:head>
                        <tr>
                            <x-table.th class="min-w-40">{{ __('payroll_runs.preview.employee') }}</x-table.th>
                            <x-table.th>{{ __('payroll_runs.runs.fields.gross') }}</x-table.th>
                            <x-table.th>{{ __('payroll_runs.runs.fields.deductions') }}</x-table.th>
                            <x-table.th>{{ __('payroll_runs.runs.fields.net') }}</x-table.th>
                            <x-table.th>{{ __('payroll_runs.runs.variance') }}</x-table.th>
                            <x-table.th>{{ __('payroll_runs.runs.flag') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($items as $item)
                        <x-table.row :data-href="route('admin.payroll-runs.items.show', [$run, $item])">
                            <x-table.td :label="__('payroll_runs.preview.employee')">
                                <a href="{{ route('admin.payroll-runs.items.show', [$run, $item]) }}" class="text-label-md text-text-link hover:underline">{{ $item->employee->name }}</a>
                                <span class="block text-caption-md text-text-tertiary"><span dir="ltr">{{ $item->employee->employee_number }}</span> · {{ $item->branch?->name }} · {{ $item->department?->name }}</span>
                            </x-table.td>
                            <x-table.td :label="__('payroll_runs.runs.fields.gross')">@include('admin.payroll._money', ['amount' => $item->gross_pay, 'currency' => null])</x-table.td>
                            <x-table.td :label="__('payroll_runs.runs.fields.deductions')">@include('admin.payroll._money', ['amount' => $item->total_deductions, 'currency' => null])</x-table.td>
                            <x-table.td :label="__('payroll_runs.runs.fields.net')" class="font-semibold">@include('admin.payroll._money', ['amount' => $item->net_pay, 'currency' => null])</x-table.td>
                            <x-table.td :label="__('payroll_runs.runs.variance')" class="whitespace-nowrap">
                                @if ($variance[$item->id] !== null)
                                    <span dir="ltr" @class(['tabular-nums', 'text-feedback-danger-text' => abs((float) $variance[$item->id]) >= 10])>{{ bccomp($variance[$item->id], '0', 1) > 0 ? '+' : '' }}{{ $variance[$item->id] }}%</span>
                                    <span class="block text-caption-md text-text-tertiary">{{ __('payroll_runs.runs.vs', ['period' => $previous[$item->employee_id]['period']]) }}</span>
                                @else
                                    <span class="text-text-tertiary">—</span>
                                @endif
                            </x-table.td>
                            <x-table.td :label="__('payroll_runs.runs.flag')">
                                @if (! $item->isCalculated())
                                    <x-badge :tone="$item->status->tone()">{{ $item->status->label() }}</x-badge>
                                @endif
                                @if ($item->moved_in_period) <x-badge tone="info">{{ __('payroll_runs.runs.flags.moved') }}</x-badge> @endif
                                @php($warnings = collect($item->warnings ?? []))
                                @if ($warnings->isNotEmpty())
                                    <x-badge :tone="$item->has_blocking_warning ? 'danger' : 'warning'" dot>{{ trans_choice('payroll_runs.runs.warning_count', $warnings->count(), ['count' => $warnings->count()]) }}</x-badge>
                                @endif
                            </x-table.td>
                        </x-table.row>
                    @empty
                        <x-table.empty :colspan="6" icon="users" :title="__('payroll_runs.runs.no_items')" />
                    @endforelse
                </x-table>
            @elseif ($tab === 'components')
                <x-card :title="__('payroll_runs.runs.by_component')" padding="none">
                    <x-table :caption="__('payroll_runs.runs.by_component')">
                        <x-slot:head>
                            <tr>
                                <x-table.th>{{ __('payroll_runs.runs.component') }}</x-table.th>
                                <x-table.th>{{ __('payroll_runs.runs.kind') }}</x-table.th>
                                <x-table.th>{{ __('payroll_runs.runs.fields.employees') }}</x-table.th>
                                <x-table.th>{{ __('payroll_runs.runs.amount') }}</x-table.th>
                            </tr>
                        </x-slot:head>
                        @forelse ($components as $row)
                            <x-table.row>
                                <x-table.td :label="__('payroll_runs.runs.component')">{{ $row['name'] }} <span class="text-caption-md text-text-tertiary" dir="ltr">{{ $row['code'] }}</span></x-table.td>
                                <x-table.td :label="__('payroll_runs.runs.kind')">{{ __('payroll.enums.kinds.'.$row['kind']) }}</x-table.td>
                                <x-table.td :label="__('payroll_runs.runs.fields.employees')" class="tabular-nums">{{ $row['employees'] }}</x-table.td>
                                <x-table.td :label="__('payroll_runs.runs.amount')">@include('admin.payroll._money', ['amount' => $row['amount'], 'currency' => $currency])</x-table.td>
                            </x-table.row>
                        @empty
                            <x-table.empty :colspan="4" icon="coins" :title="__('payroll_runs.runs.not_calculated')" />
                        @endforelse
                    </x-table>
                </x-card>
                @foreach (['by_branch' => $byBranch, 'by_department' => $byDepartment] as $title => $rows)
                    <x-card :title="__('payroll_runs.runs.'.$title)" padding="none">
                        <x-table :caption="__('payroll_runs.runs.'.$title)">
                            <x-slot:head>
                                <tr>
                                    <x-table.th>{{ __('app.common.name') }}</x-table.th>
                                    <x-table.th>{{ __('payroll_runs.runs.fields.employees') }}</x-table.th>
                                    <x-table.th>{{ __('payroll_runs.runs.fields.gross') }}</x-table.th>
                                    <x-table.th>{{ __('payroll_runs.runs.fields.deductions') }}</x-table.th>
                                    <x-table.th>{{ __('payroll_runs.runs.fields.net') }}</x-table.th>
                                </tr>
                            </x-slot:head>
                            @foreach ($rows as $row)
                                <x-table.row>
                                    <x-table.td :label="__('app.common.name')">{{ $row['name'] }}</x-table.td>
                                    <x-table.td :label="__('payroll_runs.runs.fields.employees')" class="tabular-nums">{{ $row['employees'] }}</x-table.td>
                                    <x-table.td :label="__('payroll_runs.runs.fields.gross')">@include('admin.payroll._money', ['amount' => $row['gross'], 'currency' => null])</x-table.td>
                                    <x-table.td :label="__('payroll_runs.runs.fields.deductions')">@include('admin.payroll._money', ['amount' => $row['deductions'], 'currency' => null])</x-table.td>
                                    <x-table.td :label="__('payroll_runs.runs.fields.net')">@include('admin.payroll._money', ['amount' => $row['net'], 'currency' => null])</x-table.td>
                                </x-table.row>
                            @endforeach
                        </x-table>
                    </x-card>
                @endforeach
            @elseif ($tab === 'approvals')
                <x-card :title="__('payroll.common.approval_history')">
                    @include('admin.payroll._approval-history', ['requests' => $run->approvalRequests])
                </x-card>
            @else
                <x-card :title="__('payroll_runs.runs.tabs.history')" padding="none">
                    <ol class="flex flex-col divide-y divide-border-subtle">
                        @forelse ($history as $log)
                            <li class="flex flex-col gap-0.5 px-5 py-3">
                                <span class="text-label-md text-text-primary">{{ __('payroll_runs.events.'.str_replace('payroll.', '', $log->event)) }}</span>
                                <span class="text-caption-md text-text-tertiary">{{ $log->user?->name ?? __('payroll_runs.runs.system') }} · <span dir="ltr">{{ $log->created_at->format('Y-m-d H:i') }}</span></span>
                                @if (! empty($log->meta['reason']))
                                    <span class="text-body-sm text-text-secondary">{{ $log->meta['reason'] }}</span>
                                @endif
                            </li>
                        @empty
                            <li class="px-5 py-4 text-body-sm text-text-tertiary">{{ __('payroll.common.no_history') }}</li>
                        @endforelse
                    </ol>
                </x-card>
            @endif
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('payroll_runs.runs.next_step')" data-region="run-actions">
                <div class="flex flex-col gap-3">
                    @if ($can['update'])
                        <form method="POST" action="{{ route('admin.payroll-runs.calculate', $run) }}">
                            @csrf
                            <x-button class="w-full" icon="refresh">{{ $run->calculated_at ? __('payroll_runs.runs.recalculate') : __('payroll_runs.runs.calculate') }}</x-button>
                        </form>
                    @endif
                    @if ($can['submit'])
                        <form method="POST" action="{{ route('admin.payroll-runs.submit', $run) }}" data-confirm="{{ __('payroll_runs.runs.confirm_submit') }}">
                            @csrf
                            <x-button class="w-full" variant="secondary" icon="check">{{ __('payroll_runs.runs.submit') }}</x-button>
                        </form>
                    @endif
                    @if ($can['decide'])
                        <form method="POST" action="{{ route('admin.payroll-runs.decide', [$run, 'approve']) }}" class="flex flex-col gap-2" data-validate>
                            @csrf
                            <x-form.input name="reason" id="approve-comment" maxlength="1000" :label="__('payroll.common.comment')" />
                            <x-button icon="check">{{ __('payroll.common.approve') }} · {{ $run->latestApprovalRequest?->currentStep?->name }}</x-button>
                        </form>
                        <form method="POST" action="{{ route('admin.payroll-runs.decide', [$run, 'reject']) }}" class="flex flex-col gap-2" data-validate>
                            @csrf
                            <x-form.textarea name="reason" id="reject-reason" rows="2" required maxlength="1000" :label="__('payroll.common.reject_reason')" />
                            <x-button variant="danger" icon="x-circle">{{ __('payroll.common.reject') }}</x-button>
                        </form>
                    @elseif ($run->status->value === 'pending_approval')
                        <p class="text-body-sm text-text-secondary">{{ __('payroll.common.at_step', ['step' => $run->latestApprovalRequest?->currentStep?->name ?? '—']) }}</p>
                    @endif
                    @if ($can['finalise'])
                        <form method="POST" action="{{ route('admin.payroll-runs.finalise', $run) }}" data-confirm="{{ __('payroll_runs.runs.confirm_finalise') }}" data-confirm-tone="warning">
                            @csrf
                            <x-button class="w-full" icon="lock">{{ __('payroll_runs.runs.finalise') }}</x-button>
                        </form>
                    @elseif ($run->status->value === 'approved')
                        <p class="text-body-sm text-text-secondary">{{ __('payroll_runs.runs.awaiting_finalisation') }}</p>
                    @endif
                    @if ($run->isFinalised())
                        <x-button variant="secondary" icon="file-text" :href="route('admin.payslips.index', ['run' => $run->id])">{{ __('payroll_runs.runs.view_payslips') }}</x-button>
                    @endif
                    @if ($can['update'])
                        <div class="flex flex-wrap gap-2">
                            <x-button size="sm" variant="ghost" icon="filter" :href="route('admin.payroll-runs.scope.edit', $run)">{{ __('payroll_runs.runs.change_scope') }}</x-button>
                            <form method="POST" action="{{ route('admin.payroll-runs.refresh', $run) }}">
                                @csrf
                                <x-button size="sm" variant="ghost" icon="users">{{ __('payroll_runs.runs.refresh_population') }}</x-button>
                            </form>
                        </div>
                    @endif
                    @if ($can['cancel'])
                        <details>
                            <summary class="cursor-pointer text-label-md text-feedback-danger-text">{{ __('payroll_runs.runs.cancel') }}</summary>
                            <form method="POST" action="{{ route('admin.payroll-runs.cancel', $run) }}" class="mt-2 flex flex-col gap-2" data-validate data-confirm="{{ __('payroll_runs.runs.confirm_cancel') }}" data-confirm-tone="danger">
                                @csrf
                                <x-form.input name="reason" id="cancel-reason" required maxlength="500" :label="__('payroll.fields.reason')" />
                                <x-button size="sm" variant="danger" icon="x">{{ __('payroll_runs.runs.cancel') }}</x-button>
                            </form>
                        </details>
                    @endif
                    @if ($can['reverse'])
                        <details>
                            <summary class="cursor-pointer text-label-md text-feedback-danger-text">{{ __('payroll_runs.runs.reverse') }}</summary>
                            <p class="mt-2 text-caption-md text-text-tertiary">{{ __('payroll_runs.runs.reverse_help') }}</p>
                            <form method="POST" action="{{ route('admin.payroll-runs.reverse', $run) }}" class="mt-2 flex flex-col gap-2" data-validate data-confirm="{{ __('payroll_runs.runs.confirm_reverse') }}" data-confirm-tone="danger">
                                @csrf
                                <x-form.textarea name="reason" id="reverse-reason" rows="2" required maxlength="1000" :label="__('payroll.fields.reason')" />
                                <x-button size="sm" variant="danger" icon="refresh">{{ __('payroll_runs.runs.reverse') }}</x-button>
                            </form>
                        </details>
                    @endif
                    @if ($run->status->value === 'cancelled')
                        <p class="text-body-sm text-text-secondary">{{ __('payroll_runs.runs.cancelled_note', ['name' => $run->cancelled_at?->format('Y-m-d H:i')]) }}</p>
                        @if ($run->notes)
                            <p class="text-caption-md text-text-tertiary">{{ $run->notes }}</p>
                        @endif
                    @endif
                    @if (! array_filter($can) && $run->status->isLive() && ! $run->isFinalised())
                        <p class="text-body-sm text-text-tertiary">{{ __('payroll_runs.runs.no_action') }}</p>
                    @endif
                </div>
            </x-card>

            <x-card :title="__('payroll_runs.runs.details')">
                <x-detail-list>
                    <x-detail-item :label="__('payroll_runs.runs.fields.payroll_period_id')">{{ $run->period->name }} · <span dir="ltr">{{ $run->period->rangeLabel() }}</span></x-detail-item>
                    <x-detail-item :label="__('payroll_runs.periods.fields.pay_date')"><span dir="ltr">{{ $run->period->pay_date->toDateString() }}</span></x-detail-item>
                    <x-detail-item :label="__('payroll_runs.runs.fields.scope')">{{ $run->scope_summary }}</x-detail-item>
                    <x-detail-item :label="__('payroll_runs.runs.settlement')">{{ $run->branch?->name ?? __('payroll_runs.runs.consolidated') }}</x-detail-item>
                    <x-detail-item :label="__('payroll_runs.runs.created_by')">{{ $run->creator?->name ?? '—' }} · <span dir="ltr">{{ $run->created_at->format('Y-m-d H:i') }}</span></x-detail-item>
                    @if ($run->calculated_at)
                        <x-detail-item :label="__('payroll_runs.runs.calculated_by')">{{ $run->calculator?->name ?? __('payroll_runs.runs.system') }} · <span dir="ltr">{{ $run->calculated_at->format('Y-m-d H:i') }}</span></x-detail-item>
                    @endif
                    @if ($run->submitted_at)
                        <x-detail-item :label="__('payroll_runs.runs.submitted_by')">{{ $run->submitter?->name }} · <span dir="ltr">{{ $run->submitted_at->format('Y-m-d H:i') }}</span></x-detail-item>
                    @endif
                    @if ($run->finalised_at)
                        <x-detail-item :label="__('payroll_runs.runs.finalised_by')">{{ $run->finaliser?->name }} · <span dir="ltr">{{ $run->finalised_at->format('Y-m-d H:i') }}</span></x-detail-item>
                        <x-detail-item :label="__('payroll_runs.runs.totals_hash')"><code class="break-all text-caption-md" dir="ltr">{{ $run->totals_hash }}</code></x-detail-item>
                    @endif
                    @if ($run->notes)
                        <x-detail-item :label="__('payroll_runs.runs.fields.notes')">{{ $run->notes }}</x-detail-item>
                    @endif
                </x-detail-list>
            </x-card>
        </div>
    </div>
@endsection
