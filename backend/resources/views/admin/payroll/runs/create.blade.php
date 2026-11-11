@extends('layouts.admin', ['breadcrumbs' => [['label' => __('payroll_runs.runs.title'), 'url' => route('admin.payroll-runs.index')]], 'subtitle' => __('payroll_runs.wizard.scope_subtitle')])

@section('title', __('payroll_runs.runs.new'))

@section('content')
    @include('admin.payroll.runs._wizard', ['current' => 1])

    @if ($periods->isEmpty())
        <x-empty-state icon="calendar" :title="__('payroll_runs.wizard.no_open_period')" :description="__('payroll_runs.wizard.no_open_period_help')">
            @can('viewAny', App\Models\PayrollPeriod::class)
                <x-button :href="route('admin.payroll-periods.index')" icon="calendar">{{ __('payroll_runs.periods.title') }}</x-button>
            @endcan
        </x-empty-state>
    @else
        <form method="GET" action="{{ route('admin.payroll-runs.preview') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
            <x-form.section :title="__('payroll_runs.wizard.run_details')" :description="__('payroll_runs.wizard.run_details_help')">
                <x-form.select name="payroll_period_id" required :label="__('payroll_runs.runs.fields.payroll_period_id')">
                    @foreach ($periods as $period)
                        <option value="{{ $period->id }}" @selected((int) ($selected['payroll_period_id'] ?? 0) === $period->id)>{{ $period->name }} ({{ $period->rangeLabel() }})</option>
                    @endforeach
                </x-form.select>
                <x-form.select name="type" required :label="__('payroll_runs.runs.fields.type')" :help="__('payroll_runs.wizard.type_help')">
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(($selected['type'] ?? 'regular') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input name="name" maxlength="200" :label="__('payroll_runs.runs.fields.name')" :help="__('payroll_runs.wizard.name_help')" :value="$selected['name'] ?? ''" />
                <x-form.input name="notes" maxlength="1000" :label="__('payroll_runs.runs.fields.notes')" :value="$selected['notes'] ?? ''" />
            </x-form.section>

            @include('admin.payroll.runs._scope-fields')

            <x-form.actions>
                <x-button variant="secondary" :href="route('admin.payroll-runs.index')">{{ __('app.actions.cancel') }}</x-button>
                <x-button icon-end="arrow-right">{{ __('payroll_runs.wizard.preview_population') }}</x-button>
            </x-form.actions>
        </form>
    @endif
@endsection
