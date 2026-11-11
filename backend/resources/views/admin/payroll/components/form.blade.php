@extends('layouts.admin', ['breadcrumbs' => [['label' => __('payroll.components.title'), 'url' => route('admin.salary-components.index')]]])

@section('title', $record->exists ? __('payroll.components.edit') : __('payroll.components.create'))

@section('content')
    <form method="POST" action="{{ $record->exists ? route('admin.salary-components.update', $record) : route('admin.salary-components.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate data-component-form>
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('payroll.components.identity')">
            @include('admin.structure._identity-fields')
            <x-form.select name="kind" :label="__('payroll.fields.kind')" required>
                @foreach ($kinds as $kind)
                    <option value="{{ $kind->value }}" @selected(old('kind', $record->kind?->value) === $kind->value)>{{ $kind->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="category" :label="__('payroll.fields.category')" required>
                @foreach ($categories as $category)
                    <option value="{{ $category->value }}" @selected(old('category', $record->category?->value) === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </x-form.select>
            @include('admin.structure._description-field')
        </x-form.section>

        <x-form.section :title="__('payroll.components.calculation')" :description="__('payroll.help.calculation')">
            <x-form.select name="calculation_type" :label="__('payroll.fields.calculation_type')" required data-switch>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('calculation_type', $record->calculation_type?->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form.select>
            <div data-show-for="statutory">
                <x-form.select name="statutory_type" :label="__('payroll.fields.statutory_type')">
                    <option value="">—</option>
                    @foreach ($statutoryTypes as $statutory)
                        <option value="{{ $statutory->value }}" @selected(old('statutory_type', $record->statutory_type?->value) === $statutory->value)>{{ $statutory->label() }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <div data-show-for="fixed late_minutes early_leave_minutes absent_days overtime_hours unpaid_leave_days">
                <x-form.input type="number" name="amount" step="0.0001" min="0" dir="ltr" inputmode="decimal" :label="__('payroll.fields.amount')"
                    :value="old('amount', App\Services\Payroll\PayrollMath::trim($record->amount))" :help="__('payroll.help.amount_fixed').' '.__('payroll.help.amount_quantity')" />
            </div>
            <div data-show-for="percentage late_minutes early_leave_minutes absent_days overtime_hours unpaid_leave_days">
                <x-form.input type="number" name="rate" step="0.0001" min="0" dir="ltr" inputmode="decimal" :label="__('payroll.fields.rate')"
                    :value="old('rate', App\Services\Payroll\PayrollMath::trim($record->rate))" :help="__('payroll.help.rate_percentage').' '.__('payroll.help.rate_quantity')" />
            </div>
            <div data-show-for="percentage">
                <x-form.select name="base_component_id" :label="__('payroll.fields.base_component_id')" :help="__('payroll.help.base')">
                    <option value="">{{ __('payroll.common.basic_salary') }}</option>
                    @foreach ($bases as $base)
                        <option value="{{ $base->id }}" @selected((string) old('base_component_id', $record->base_component_id) === (string) $base->id)>{{ $base->code }} · {{ $base->name }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <div class="md:col-span-2" data-show-for="overtime_hours">
                <input type="hidden" name="use_policy_multiplier" value="0">
                <x-form.switch name="use_policy_multiplier" :label="__('payroll.fields.use_policy_multiplier')" :description="__('payroll.help.use_policy_multiplier')" :checked="(bool) old('use_policy_multiplier', $record->use_policy_multiplier)" />
            </div>
        </x-form.section>

        <x-form.section :title="__('payroll.components.treatment')" :description="__('payroll.components.treatment_help')">
            @foreach ([['is_taxable', null], ['is_insurable', null], ['prorate_partial_period', __('payroll.help.prorate')], ['requires_approval', __('payroll.help.requires_approval')], ['show_on_payslip', null]] as [$name, $help])
                <div class="md:col-span-2">
                    <input type="hidden" name="{{ $name }}" value="0">
                    <x-form.switch :name="$name" :label="__('payroll.fields.'.$name)" :description="$help" :checked="(bool) old($name, $record->{$name})" />
                </div>
            @endforeach
            <x-form.select name="rounding" :label="__('payroll.fields.rounding')" required>
                @foreach ($roundings as $rounding)
                    <option value="{{ $rounding->value }}" @selected(old('rounding', $record->rounding?->value) === $rounding->value)>{{ $rounding->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input type="number" name="rounding_precision" min="0" max="4" :label="__('payroll.fields.rounding_precision')" :value="old('rounding_precision', $record->rounding_precision)"
                :help="__('payroll.help.rounding_precision', ['value' => setting('payroll.rounding_precision')])" />
            <x-form.input type="number" name="display_order" min="0" max="9999" required :label="__('payroll.fields.display_order')" :value="old('display_order', $record->display_order)" />
            @include('admin.structure._active-switch')
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.salary-components.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
