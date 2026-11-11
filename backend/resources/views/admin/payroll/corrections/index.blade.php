@extends('layouts.admin', ['subtitle' => __('payroll_runs.corrections.subtitle')])

@section('title', __('payroll_runs.corrections.title'))

@section('page-actions')
    @can('create', App\Models\PayrollAdjustment::class)
        <form method="POST" action="{{ route('admin.payroll-corrections.scan') }}">
            @csrf
            <x-button variant="secondary" icon="refresh">{{ __('payroll_runs.corrections.scan') }}</x-button>
        </form>
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
    <x-alert tone="info">{{ __('payroll_runs.corrections.help') }}</x-alert>

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex min-w-0 flex-col gap-4 lg:col-span-2">
            <x-filter-bar :action="route('admin.payroll-corrections.index')" :reset="route('admin.payroll-corrections.index')">
                <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-52" data-filter-debounce />
                <x-form.select name="status" :label="__('payroll_runs.corrections.fields.status')" hide-label class="md:w-44">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </x-form.select>
                <x-form.select name="source" :label="__('payroll_runs.corrections.fields.source')" hide-label class="md:w-44">
                    <option value="">{{ __('payroll_runs.corrections.fields.source') }}: {{ __('app.common.all') }}</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ $source->label() }}</option>
                    @endforeach
                </x-form.select>
            </x-filter-bar>

            <x-table :caption="__('payroll_runs.corrections.title')" data-region="adjustments">
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('payroll_runs.corrections.fields.reference') }}</x-table.th>
                        <x-table.th class="min-w-48">{{ __('payroll_runs.corrections.fields.employee_id') }}</x-table.th>
                        <x-table.th class="min-w-56">{{ __('payroll_runs.corrections.fields.reason') }}</x-table.th>
                        <x-table.th>{{ __('payroll_runs.corrections.fields.amount') }}</x-table.th>
                        <x-table.th>{{ __('payroll_runs.corrections.fields.status') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($adjustments as $adjustment)
                    <x-table.row>
                        <x-table.td :label="__('payroll_runs.corrections.fields.reference')" class="whitespace-nowrap">
                            <span class="text-label-md" dir="ltr">{{ $adjustment->reference }}</span>
                            <span class="block text-caption-md text-text-tertiary">{{ $adjustment->source->label() }} · {{ $adjustment->originalPeriod?->code ?? '—' }}</span>
                        </x-table.td>
                        <x-table.td :label="__('payroll_runs.corrections.fields.employee_id')">{{ $adjustment->employee?->label() }}</x-table.td>
                        <x-table.td :label="__('payroll_runs.corrections.fields.reason')">
                            <span class="text-label-md text-text-primary">{{ $adjustment->name() }}</span>
                            <span class="block text-body-sm text-text-secondary">{{ $adjustment->reason }}</span>
                            @if ($adjustment->dismiss_reason)
                                <span class="block text-caption-md text-feedback-danger-text">{{ $adjustment->dismiss_reason }}</span>
                            @endif
                        </x-table.td>
                        <x-table.td :label="__('payroll_runs.corrections.fields.amount')">
                            @include('admin.payroll._money', ['amount' => $adjustment->amount, 'currency' => $currency])
                            <span class="block text-caption-md text-text-tertiary">{{ __('payroll.enums.kinds.'.$adjustment->kind->value) }}</span>
                        </x-table.td>
                        <x-table.td :label="__('payroll_runs.corrections.fields.status')">
                            <x-badge :tone="$adjustment->status->tone()">{{ $adjustment->status->label() }}</x-badge>
                            @if ($adjustment->carrier)
                                <a class="block text-caption-md text-text-link hover:underline" href="{{ route('admin.payroll-runs.show', $adjustment->carrier->payroll_run_id) }}" dir="ltr">{{ $adjustment->carrier->run?->reference }}</a>
                            @endif
                            @can('dismiss', $adjustment)
                                <details class="mt-1">
                                    <summary class="cursor-pointer text-caption-md text-text-link">{{ __('payroll_runs.corrections.dismiss') }}</summary>
                                    <form method="POST" action="{{ route('admin.payroll-corrections.dismiss', $adjustment) }}" class="mt-2 flex flex-col gap-2" data-validate>
                                        @csrf
                                        <x-form.input name="reason" :id="'dismiss-'.$adjustment->id" required maxlength="1000" :label="__('payroll.fields.reason')" />
                                        <x-button size="sm" variant="danger" icon="x">{{ __('payroll_runs.corrections.dismiss') }}</x-button>
                                    </form>
                                </details>
                            @endcan
                        </x-table.td>
                    </x-table.row>
                @empty
                    <x-table.empty :colspan="5" icon="refresh" :title="__('payroll_runs.corrections.empty')" />
                @endforelse
                <x-slot:footer>
                    <x-pagination :paginator="$adjustments" />
                </x-slot:footer>
            </x-table>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            @can('create', App\Models\PayrollRun::class)
                <x-card :title="__('payroll_runs.corrections.run_title')" :subtitle="__('payroll_runs.corrections.run_help', ['count' => $pendingFree])" data-region="correction-run">
                    @if ($pendingFree > 0 && $openPeriods->isNotEmpty())
                        <form method="POST" action="{{ route('admin.payroll-corrections.run') }}" class="flex flex-col gap-3">
                            @csrf
                            <x-form.select name="payroll_period_id" id="correction-period" required :label="__('payroll_runs.runs.fields.payroll_period_id')">
                                @foreach ($openPeriods as $period)
                                    <option value="{{ $period->id }}">{{ $period->name }}</option>
                                @endforeach
                            </x-form.select>
                            <x-button icon="plus">{{ __('payroll_runs.corrections.create_run') }}</x-button>
                        </form>
                    @else
                        <p class="text-body-sm text-text-tertiary">{{ __('payroll_runs.corrections.nothing_to_run') }}</p>
                    @endif
                </x-card>
            @endcan

            @can('create', App\Models\PayrollAdjustment::class)
                <x-card :title="__('payroll_runs.corrections.manual_title')" :subtitle="__('payroll_runs.corrections.manual_help')" data-region="manual-adjustment">
                    <form method="POST" action="{{ route('admin.payroll-corrections.store') }}" class="flex flex-col gap-3" data-validate>
                        @csrf
                        <x-form.select name="employee_id" required :label="__('payroll_runs.corrections.fields.employee_id')">
                            <option value="">—</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((int) old('employee_id') === $employee->id)>{{ $employee->label() }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.select name="salary_component_id" :label="__('payroll_runs.corrections.fields.salary_component_id')" :help="__('payroll_runs.corrections.component_help')">
                            <option value="">{{ __('payroll_runs.corrections.no_component') }}</option>
                            @foreach ($components as $component)
                                <option value="{{ $component->id }}" @selected((int) old('salary_component_id') === $component->id)>{{ $component->code }} · {{ $component->name }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.select name="kind" :label="__('payroll_runs.corrections.fields.kind')">
                            <option value="earning">{{ __('payroll.enums.kinds.earning') }}</option>
                            <option value="deduction" @selected(old('kind') === 'deduction')>{{ __('payroll.enums.kinds.deduction') }}</option>
                        </x-form.select>
                        <x-form.input type="number" name="amount" step="0.01" dir="ltr" required :label="__('payroll_runs.corrections.fields.amount')" :help="__('payroll_runs.corrections.amount_help')" :value="old('amount')" />
                        <x-form.select name="original_period_id" :label="__('payroll_runs.corrections.fields.original_period_id')">
                            <option value="">—</option>
                            @foreach ($periods as $period)
                                <option value="{{ $period->id }}" @selected((int) old('original_period_id') === $period->id)>{{ $period->name }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.textarea name="reason" rows="2" required maxlength="1000" :label="__('payroll_runs.corrections.fields.reason')">{{ old('reason') }}</x-form.textarea>
                        <x-button icon="plus">{{ __('payroll_runs.corrections.create') }}</x-button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
@endsection
