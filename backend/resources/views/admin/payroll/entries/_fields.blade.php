{{--
    Entry fields shared by the single and bulk forms (inside an x-form.section grid).
    Variables: $kind, $components, $bases, $calculations, $recurrences.
--}}
<x-form.select name="salary_component_id" :label="__('payroll.fields.salary_component_id')" required>
    <option value="">—</option>
    @foreach ($components as $component)
        <option value="{{ $component->id }}" @selected((string) old('salary_component_id') === (string) $component->id)>{{ $component->code }} · {{ $component->name }}{{ $component->requires_approval ? ' · '.__('payroll.fields.requires_approval') : '' }}</option>
    @endforeach
</x-form.select>
<x-form.select name="recurrence" :label="__('payroll.fields.recurrence')" required data-switch="recurrence" :help="__('payroll.help.entry_recurrence')">
    @foreach ($recurrences as $recurrence)
        <option value="{{ $recurrence->value }}" @selected(old('recurrence', 'recurring') === $recurrence->value)>{{ $recurrence->label() }}</option>
    @endforeach
</x-form.select>
<div data-show-for="recurring one_time" data-switch-group="recurrence">
    <x-form.select name="calculation" :label="__('payroll.fields.calculation')" required data-switch="calculation">
        @foreach ($calculations as $calculation)
            <option value="{{ $calculation->value }}" @selected(old('calculation', 'fixed') === $calculation->value)>{{ $calculation->label() }}</option>
        @endforeach
    </x-form.select>
</div>
<div data-show-for="fixed" data-switch-group="calculation" data-hide-when="recurrence:instalment">
    <x-form.input type="number" name="amount" step="0.01" min="0" dir="ltr" inputmode="decimal" :label="__('payroll.fields.amount').' ('.setting('localisation.currency_code').')'" :value="old('amount')" />
</div>
<div data-show-for="percentage" data-switch-group="calculation" data-hide-when="recurrence:instalment">
    <x-form.input type="number" name="rate" step="0.0001" min="0" dir="ltr" inputmode="decimal" :label="__('payroll.fields.rate').' %'" :value="old('rate')" />
</div>
<div data-show-for="percentage" data-switch-group="calculation" data-hide-when="recurrence:instalment">
    <x-form.select name="base_component_id" :label="__('payroll.fields.base_component_id')">
        <option value="">{{ __('payroll.common.basic_salary') }}</option>
        @foreach ($bases as $base)
            @continue($base->calculation_type === App\Enums\PayCalculationType::Basic)
            <option value="{{ $base->id }}" @selected((string) old('base_component_id') === (string) $base->id)>{{ $base->code }} · {{ $base->name }}</option>
        @endforeach
    </x-form.select>
</div>
@if (collect($recurrences)->contains(App\Enums\PayEntryRecurrence::Instalment))
    <div data-show-for="instalment" data-switch-group="recurrence">
        <x-form.input type="number" name="total_amount" step="0.01" min="0" dir="ltr" inputmode="decimal" :label="__('payroll.fields.total_amount')" :value="old('total_amount')" />
    </div>
    <div data-show-for="instalment" data-switch-group="recurrence">
        <x-form.input type="number" name="instalment_count" min="1" max="120" :label="__('payroll.fields.instalment_count')" :value="old('instalment_count')" />
    </div>
@endif
<x-form.input type="date" name="start_date" required :label="__('payroll.fields.start_date')" :value="old('start_date', now()->startOfMonth()->toDateString())" />
<div data-show-for="recurring" data-switch-group="recurrence">
    <x-form.input type="date" name="end_date" :label="__('payroll.fields.end_date')" :value="old('end_date')" :help="__('payroll.common.open_ended')" />
</div>
<div class="md:col-span-2">
    <x-form.textarea name="reason" :label="__('payroll.fields.reason')" maxlength="1000" rows="2" :required="$kind === App\Enums\PayComponentKind::Deduction">{{ old('reason') }}</x-form.textarea>
</div>
<p class="md:col-span-2 text-caption-md text-text-tertiary">{{ __('payroll.help.treatment_inherit') }}</p>
@foreach (['is_taxable', 'is_insurable', 'prorate'] as $flag)
    <div @if ($flag === 'prorate') data-show-for="recurring" data-switch-group="recurrence" @endif>
        <x-form.select :name="$flag" :label="__('payroll.fields.'.$flag)">
            <option value="">{{ __('payroll.common.inherit', ['value' => '—']) }}</option>
            <option value="1" @selected(old($flag) === '1')>{{ __('payroll.common.yes') }}</option>
            <option value="0" @selected(old($flag) === '0')>{{ __('payroll.common.no') }}</option>
        </x-form.select>
    </div>
@endforeach
<div class="md:col-span-2">
    <x-form.input name="notes" maxlength="1000" :label="__('payroll.fields.notes')" :value="old('notes')" />
</div>
