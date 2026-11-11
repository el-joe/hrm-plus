{{-- One tax bracket row. Variables: $index, $bracket (array). --}}
<div class="grid gap-3 rounded-md border border-border-subtle p-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end" data-repeater-row>
    <x-form.input type="number" :name="'brackets['.$index.'][lower_bound]'" :id="'bracket-'.$index.'-lower'" step="0.01" min="0" dir="ltr" :label="__('payroll.fields.lower_bound')" :value="$bracket['lower_bound'] ?? null" />
    <x-form.input type="number" :name="'brackets['.$index.'][upper_bound]'" :id="'bracket-'.$index.'-upper'" step="0.01" min="0" dir="ltr" :label="__('payroll.fields.upper_bound')" :value="$bracket['upper_bound'] ?? null" />
    <x-form.input type="number" :name="'brackets['.$index.'][rate]'" :id="'bracket-'.$index.'-rate'" step="0.0001" min="0" max="100" dir="ltr" :label="__('payroll.fields.rate').' %'" :value="App\Services\Payroll\PayrollMath::trim(isset($bracket['rate']) ? (string) $bracket['rate'] : null)" />
    <div class="flex justify-end">
        <x-icon-button type="button" icon="trash" size="sm" variant="danger" :label="__('payroll.structures.remove_line')" data-repeater-remove hidden data-js-only />
    </div>
</div>
