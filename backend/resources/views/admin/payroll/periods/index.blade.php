@extends('layouts.admin', ['subtitle' => __('payroll_runs.periods.subtitle')])

@section('title', __('payroll_runs.periods.title'))

@section('content')
    @if ($errors->any())
        <x-alert tone="danger">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('payroll_runs.periods.title')" padding="none" class="lg:col-span-2">
            <x-table :caption="__('payroll_runs.periods.title')">
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('payroll_runs.periods.fields.name_en') }}</x-table.th>
                        <x-table.th>{{ __('payroll_runs.periods.fields.dates') }}</x-table.th>
                        <x-table.th>{{ __('payroll_runs.periods.fields.pay_date') }}</x-table.th>
                        <x-table.th>{{ __('payroll_runs.periods.fields.runs') }}</x-table.th>
                        <x-table.th>{{ __('payroll_runs.periods.fields.status') }}</x-table.th>
                        <x-table.th><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($periods as $period)
                    <x-table.row>
                        <x-table.td :label="__('payroll_runs.periods.fields.name_en')">
                            <a class="text-label-md text-text-link hover:underline" href="{{ route('admin.payroll-runs.index', ['period' => $period->id]) }}">{{ $period->name }}</a>
                            <span class="block text-caption-md text-text-tertiary"><span dir="ltr">{{ $period->code }}</span> · {{ $period->type->label() }}</span>
                        </x-table.td>
                        <x-table.td :label="__('payroll_runs.periods.fields.dates')" class="whitespace-nowrap"><span dir="ltr">{{ $period->rangeLabel() }}</span></x-table.td>
                        <x-table.td :label="__('payroll_runs.periods.fields.pay_date')" class="whitespace-nowrap">
                            <span dir="ltr">{{ $period->pay_date->toDateString() }}</span>
                            @if ($period->cutoff_date)
                                <span class="block text-caption-md text-text-tertiary">{{ __('payroll_runs.periods.cutoff', ['date' => $period->cutoff_date->toDateString()]) }}</span>
                            @endif
                        </x-table.td>
                        <x-table.td :label="__('payroll_runs.periods.fields.runs')" class="tabular-nums">{{ $period->runs_count }}</x-table.td>
                        <x-table.td :label="__('payroll_runs.periods.fields.status')"><x-badge :tone="$period->status->tone()">{{ $period->status->label() }}</x-badge></x-table.td>
                        <x-table.td :label="__('app.common.actions')" class="whitespace-nowrap">
                            @can('manage', $period)
                                <div class="flex flex-wrap gap-2">
                                    <x-button type="button" size="sm" variant="ghost" icon="pencil" data-modal-open="period-edit-{{ $period->id }}" hidden data-js-only>{{ __('app.actions.edit') }}</x-button>
                                    @if ($period->isOpen())
                                        <form method="POST" action="{{ route('admin.payroll-periods.close', $period) }}" data-confirm="{{ __('payroll_runs.periods.confirm_close') }}" data-confirm-tone="warning">
                                            @csrf
                                            <x-button size="sm" variant="ghost" icon="lock">{{ __('payroll_runs.periods.close') }}</x-button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.payroll-periods.reopen', $period) }}">
                                            @csrf
                                            <x-button size="sm" variant="ghost" icon="refresh">{{ __('payroll_runs.periods.reopen') }}</x-button>
                                        </form>
                                    @endif
                                </div>
                                <x-modal :id="'period-edit-'.$period->id" :title="__('payroll_runs.periods.edit', ['name' => $period->name])" size="sm">
                                    <form method="POST" action="{{ route('admin.payroll-periods.update', $period) }}" class="flex flex-col gap-3" data-validate>
                                        @csrf
                                        @method('PUT')
                                        <x-form.input type="date" name="pay_date" :id="'pay-date-'.$period->id" required :label="__('payroll_runs.periods.fields.pay_date')" :value="$period->pay_date->toDateString()" />
                                        <x-form.input type="date" name="cutoff_date" :id="'cutoff-'.$period->id" :label="__('payroll_runs.periods.fields.cutoff_date')" :value="$period->cutoff_date?->toDateString()" />
                                        <x-form.input name="notes" :id="'notes-'.$period->id" maxlength="1000" :label="__('payroll_runs.periods.fields.notes')" :value="$period->notes" />
                                        <x-button icon="check">{{ __('app.actions.save') }}</x-button>
                                    </form>
                                </x-modal>
                            @endcan
                        </x-table.td>
                    </x-table.row>
                @empty
                    <x-table.empty :colspan="6" icon="calendar" :title="__('payroll_runs.periods.empty')" />
                @endforelse
                <x-slot:footer>
                    <x-pagination :paginator="$periods" />
                </x-slot:footer>
            </x-table>
        </x-card>

        @can('manage', App\Models\PayrollPeriod::class)
            <x-card :title="__('payroll_runs.periods.new')" :subtitle="__('payroll_runs.periods.new_help', ['day' => setting('payroll.period_start_day')])" data-region="period-form">
                <form method="POST" action="{{ route('admin.payroll-periods.store') }}" class="flex flex-col gap-3" data-validate>
                    @csrf
                    <x-form.select name="type" id="period-type" :label="__('payroll_runs.periods.fields.type')" required data-switch>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(old('type', 'monthly') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </x-form.select>
                    <div data-show-for="monthly">
                        <x-form.input type="month" name="month" :label="__('payroll_runs.periods.fields.month')" :value="old('month', $nextMonth)" />
                    </div>
                    <div data-show-for="custom" class="flex flex-col gap-3">
                        <x-form.input name="code" maxlength="30" dir="ltr" :label="__('payroll_runs.periods.fields.code')" :value="old('code')" />
                        <x-form.input name="name_ar" maxlength="150" dir="rtl" :label="__('payroll_runs.periods.fields.name_ar')" :value="old('name_ar')" />
                        <x-form.input name="name_en" maxlength="150" dir="ltr" :label="__('payroll_runs.periods.fields.name_en')" :value="old('name_en')" />
                        <x-form.input type="date" name="start_date" :label="__('payroll_runs.periods.fields.start_date')" :value="old('start_date')" />
                        <x-form.input type="date" name="end_date" :label="__('payroll_runs.periods.fields.end_date')" :value="old('end_date')" />
                    </div>
                    <x-form.input type="date" name="pay_date" :label="__('payroll_runs.periods.fields.pay_date')" :help="__('payroll_runs.periods.pay_date_help')" :value="old('pay_date')" />
                    <x-form.input type="date" name="cutoff_date" :label="__('payroll_runs.periods.fields.cutoff_date')" :help="__('payroll_runs.periods.cutoff_help')" :value="old('cutoff_date')" />
                    <x-button icon="plus">{{ __('payroll_runs.periods.create') }}</x-button>
                </form>
            </x-card>
        @endcan
    </div>
@endsection
