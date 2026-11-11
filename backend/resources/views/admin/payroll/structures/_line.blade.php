{{-- One structure line row. Variables: $index (int|'__INDEX__'), $line (array), $components. --}}
<div class="grid gap-3 rounded-md border border-border-subtle p-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_2fr_6rem_auto] lg:items-end" data-repeater-row>
    <x-form.select :name="'lines['.$index.'][salary_component_id]'" :id="'line-'.$index.'-component'" :label="__('payroll.fields.salary_component_id')" :error-keys="['lines.'.$index.'.salary_component_id']">
        <option value="">—</option>
        @foreach ($components as $component)
            <option value="{{ $component->id }}" @selected((string) ($line['salary_component_id'] ?? '') === (string) $component->id)>{{ $component->code }} · {{ $component->name }} ({{ $component->calculation_type->label() }})</option>
        @endforeach
    </x-form.select>
    <x-form.input type="number" :name="'lines['.$index.'][amount]'" :id="'line-'.$index.'-amount'" step="0.0001" min="0" dir="ltr" :label="__('payroll.fields.amount')"
        :value="App\Services\Payroll\PayrollMath::trim(isset($line['amount']) ? (string) $line['amount'] : null)" :placeholder="__('payroll.structures.override')" />
    <x-form.input type="number" :name="'lines['.$index.'][rate]'" :id="'line-'.$index.'-rate'" step="0.0001" min="0" dir="ltr" :label="__('payroll.fields.rate')"
        :value="App\Services\Payroll\PayrollMath::trim(isset($line['rate']) ? (string) $line['rate'] : null)" :placeholder="__('payroll.structures.override')" />
    <x-form.select :name="'lines['.$index.'][base_component_id]'" :id="'line-'.$index.'-base'" :label="__('payroll.fields.base_component_id')" :error-keys="['lines.'.$index.'.base_component_id']">
        <option value="">{{ __('payroll.common.basic_salary') }}</option>
        @foreach ($components as $component)
            <option value="{{ $component->id }}" @selected((string) ($line['base_component_id'] ?? '') === (string) $component->id)>{{ $component->code }} · {{ $component->name }}</option>
        @endforeach
    </x-form.select>
    <x-form.input type="number" :name="'lines['.$index.'][sort_order]'" :id="'line-'.$index.'-order'" min="0" max="9999" :label="__('payroll.fields.sort_order')" :value="$line['sort_order'] ?? null" />
    <div class="flex justify-end">
        <x-icon-button type="button" icon="trash" size="sm" variant="danger" :label="__('payroll.structures.remove_line')" data-repeater-remove hidden data-js-only />
    </div>
</div>
