{{-- Loan request fields shared by the admin "on behalf" form and the portal. Variables: $types, $startMonth, optional $limits (by type value). --}}
<x-form.select name="type" :label="__('payroll.loans.fields.type')" required>
    @foreach ($types as $type)
        <option value="{{ $type->value }}" @selected(old('type', 'loan') === $type->value)>{{ $type->label() }}</option>
    @endforeach
</x-form.select>
<x-form.input type="number" name="amount" step="0.01" min="0.01" dir="ltr" inputmode="decimal" required :label="__('payroll.loans.fields.amount').' ('.setting('localisation.currency_code').')'" :value="old('amount')" />
<x-form.input type="number" name="instalments" min="1" max="120" required :label="__('payroll.loans.fields.instalments')" :value="old('instalments', 1)" />
<x-form.input type="month" name="start_month" required :label="__('payroll.loans.fields.start_month')" :value="old('start_month', $startMonth)" :min="now()->format('Y-m')" />
<div class="md:col-span-2">
    <x-form.textarea name="reason" :label="__('payroll.loans.fields.reason')" maxlength="1000" rows="2">{{ old('reason') }}</x-form.textarea>
</div>
@if (! empty($limits))
    <ul class="md:col-span-2 flex flex-col gap-1 rounded-md bg-surface-subtle p-3 text-caption-md text-text-secondary">
        @foreach ($types as $type)
            <li><strong class="text-text-primary">{{ $type->label() }}:</strong>
                @include('admin.payroll.loans._limits', ['limit' => $limits[$type->value]])</li>
        @endforeach
    </ul>
@endif
