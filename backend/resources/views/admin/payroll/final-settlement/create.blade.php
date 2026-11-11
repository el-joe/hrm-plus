@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('app.nav.offboarding'), 'url' => route('admin.offboarding.index')],
    ['label' => '#'.$case->id, 'url' => route('admin.offboarding.show', $case)],
], 'subtitle' => $employee->label()])

@section('title', __('payroll_runs.final_settlement.title'))

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

    @if ($existing && ! in_array($existing->status->value, ['cancelled', 'reversed'], true))
        <x-alert tone="info">{{ __('payroll_runs.final_settlement.exists') }} <a class="text-text-link hover:underline" href="{{ route('admin.payroll-runs.show', $existing) }}" dir="ltr">{{ $existing->reference }}</a></x-alert>
    @else
        <x-alert tone="info">{{ __('payroll_runs.final_settlement.help', ['date' => $lastDay]) }}</x-alert>

        <form method="POST" action="{{ route('admin.offboarding.final-settlement.store', $case) }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
            @csrf
            <x-form.section :title="__('payroll_runs.final_settlement.encashment')" :description="__('payroll_runs.final_settlement.encashment_help')">
                @forelse ($encashable as $row)
                    <x-form.input type="number" :name="'encash['.$row['type']->id.']'" :id="'encash-'.$row['type']->id" step="0.5" min="0"
                        :max="$row['max'] !== null ? min((float) $row['max'], (float) $row['available']) : $row['available']" dir="ltr"
                        :label="$row['type']->name" :help="__('payroll_runs.final_settlement.available', ['days' => App\Services\Payroll\PayrollMath::trim($row['available']), 'max' => $row['max'] !== null ? App\Services\Payroll\PayrollMath::trim($row['max']) : '—'])"
                        :value="old('encash.'.$row['type']->id, $row['max'] !== null ? App\Services\Payroll\PayrollMath::min($row['max'], $row['available']) : $row['available'])" />
                @empty
                    <p class="md:col-span-2 text-body-sm text-text-tertiary">{{ __('payroll_runs.final_settlement.no_encashable') }}</p>
                @endforelse
            </x-form.section>

            <x-form.section :title="__('payroll_runs.final_settlement.loans')" :description="__('payroll_runs.final_settlement.loans_help')">
                <ul class="md:col-span-2 flex flex-col gap-1 text-body-sm text-text-secondary">
                    @forelse ($loans as $instalment)
                        <li><span dir="ltr">{{ $instalment->loanReference }}</span> · #{{ $instalment->sequence }} · <span dir="ltr">{{ $instalment->dueDate }}</span> · @include('admin.payroll._money', ['amount' => $instalment->amount])</li>
                    @empty
                        <li class="text-text-tertiary">{{ __('payroll_runs.final_settlement.no_loans') }}</li>
                    @endforelse
                </ul>
                <x-form.input name="notes" maxlength="1000" class="md:col-span-2" :label="__('payroll_runs.runs.fields.notes')" :value="old('notes')" />
            </x-form.section>

            <x-form.actions>
                <x-button variant="secondary" :href="route('admin.offboarding.show', $case)">{{ __('app.actions.cancel') }}</x-button>
                <x-button icon="check" data-confirm="{{ __('payroll_runs.final_settlement.confirm') }}">{{ __('payroll_runs.final_settlement.create') }}</x-button>
            </x-form.actions>
        </form>
    @endif
@endsection
