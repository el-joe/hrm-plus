@extends('layouts.admin', [
    'breadcrumbs' => [
        ['label' => __('disciplinary.cases.title'), 'url' => route('admin.disciplinary-cases.index')],
        ['label' => $case->reference, 'url' => route('admin.disciplinary-cases.show', $case)],
    ],
    'subtitle' => $case->employee->label().' · '.$case->violationType->name,
])

@section('title', __('disciplinary.actions.propose_title', ['reference' => $case->reference]))

@section('content')
    <x-alert tone="info" :title="__('disciplinary.actions.suggestion', ['type' => $suggestion['type']->label()])">
        {{ $suggestion['escalated'] ? __('disciplinary.actions.suggestion_escalated', ['count' => $activeWarnings->count()]) : __('disciplinary.actions.suggestion_first', ['type' => $suggestion['type']->label()]) }}
        {{ __('disciplinary.actions.validity_help', ['months' => $validityMonths]) }}
        @if ($activeWarnings->isNotEmpty())
            <ul class="mt-2 list-disc ps-5">
                @foreach ($activeWarnings as $warning)
                    <li>{{ $warning->type->label() }} · <span dir="ltr">{{ $warning->disciplinaryCase->reference }}</span> · {{ __('disciplinary.actions.warning_until', ['date' => $warning->expires_on->toDateString()]) }}</li>
                @endforeach
            </ul>
        @endif
    </x-alert>

    <form method="POST" action="{{ route('admin.disciplinary-cases.actions.store', $case) }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        <x-form.section :title="__('disciplinary.fields.action')">
            <x-form.select name="type" :label="__('disciplinary.fields.type')" required data-switch="action">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $suggestion['type']->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input type="date" name="effective_date" :label="__('disciplinary.fields.effective_date')" :value="old('effective_date', now()->toDateString())" required />
            <div class="md:col-span-2">
                <x-form.textarea name="details" :label="__('disciplinary.fields.details')" rows="5" maxlength="5000" required>{{ old('details') }}</x-form.textarea>
            </div>
            <div class="md:col-span-2" data-show-for="verbal_warning written_warning final_warning" data-switch-group="action">
                <x-form.textarea name="justification" :label="__('disciplinary.fields.justification')" :help="__('disciplinary.actions.justification_help')" rows="2" maxlength="2000">{{ old('justification') }}</x-form.textarea>
            </div>
        </x-form.section>

        <x-form.section :title="__('disciplinary.actions.suspension')" data-show-for="suspension" data-switch-group="action">
            <x-form.input type="date" name="suspension_start" :label="__('disciplinary.fields.suspension_start')" :value="old('suspension_start')" />
            <x-form.input type="date" name="suspension_end" :label="__('disciplinary.fields.suspension_end')" :value="old('suspension_end')" />
            <div class="md:col-span-2">
                <input type="hidden" name="suspension_paid" value="0">
                <x-form.switch name="suspension_paid" :label="__('disciplinary.fields.suspension_paid')" :checked="(bool) old('suspension_paid')" />
            </div>
        </x-form.section>

        <x-form.section :title="__('disciplinary.actions.deduction')" :description="__('disciplinary.actions.deduction_help')" data-show-for="salary_deduction suspension" data-switch-group="action">
            <x-form.input type="number" name="deduction_amount" step="0.01" min="0.01" dir="ltr" :label="__('disciplinary.fields.deduction_amount').' ('.$currency.')'"
                :value="old('deduction_amount')" :help="__('disciplinary.actions.unpaid_deduction_help')" />
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.disciplinary-cases.show', $case)">{{ __('app.actions.cancel') }}</x-button>
            <x-button icon="check">{{ __('disciplinary.actions.propose') }}</x-button>
        </x-form.actions>
    </form>
@endsection
