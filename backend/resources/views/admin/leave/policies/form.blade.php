@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('leave.policies.title'), 'url' => route('admin.leave-policies.index')]],
    'subtitle' => $source ? __('leave.policies.new_version_of', ['name' => $source->name]) : null,
])

@section('title', $record->exists ? __('leave.policies.edit') : __('leave.policies.create'))

@php
    $tiers = old('service_tiers', $record->tiers());
    $tiers = array_pad(array_values((array) $tiers), max(4, count((array) $tiers) + 1), ['min_years' => '', 'days' => '']);
    $scopeType = old('scope_type', $record->scope_type?->value);
    $scopeId = (string) old('scope_id', $record->scope_id);
@endphp

@section('content')
    <form method="POST" action="{{ $record->exists ? route('admin.leave-policies.update', $record) : route('admin.leave-policies.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        @if ($source)
            <x-alert tone="info">{{ __('leave.policies.new_version_help', ['code' => $source->code]) }}</x-alert>
        @endif

        <x-form.section :title="__('leave.policies.identity')">
            @include('admin.structure._identity-fields')
            <x-form.select name="leave_type_id" :label="__('leave.fields.leave_type_id')" required class="md:col-span-2">
                <option value="">—</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected((string) old('leave_type_id', $record->leave_type_id) === (string) $type->id)>{{ $type->code }} · {{ $type->name }}</option>
                @endforeach
            </x-form.select>
        </x-form.section>

        <x-form.section :title="__('leave.policies.applies_to')" :description="__('leave.policies.applies_to_help')">
            <x-form.select name="scope_type" :label="__('leave.fields.scope_type')" required>
                @foreach ($scopes as $scope)
                    <option value="{{ $scope->value }}" @selected($scopeType === $scope->value)>{{ $scope->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="scope_id" :label="__('leave.fields.scope_id')" :help="__('leave.help.scope_id')">
                <option value="">—</option>
                <optgroup label="{{ __('leave.enums.policy_scopes.employment_type') }}">
                    @foreach ($employmentTypes as $type)
                        <option value="{{ $type->id }}" @selected($scopeType === 'employment_type' && $scopeId === (string) $type->id)>{{ $type->code }} · {{ $type->name }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="{{ __('leave.enums.policy_scopes.employee_group') }}">
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected($scopeType === 'employee_group' && $scopeId === (string) $group->id)>{{ $group->code }} · {{ $group->name }}</option>
                    @endforeach
                </optgroup>
            </x-form.select>
            <x-form.input type="date" name="effective_from" :label="__('leave.fields.effective_from')" :value="old('effective_from', $record->effective_from?->format('Y-m-d'))" required />
            <x-form.input type="date" name="effective_to" :label="__('leave.fields.effective_to')" :value="old('effective_to', $record->effective_to?->format('Y-m-d'))" :help="__('leave.help.effective_to')" />
        </x-form.section>

        <x-form.section :title="__('leave.policies.entitlement')" :description="__('leave.policies.entitlement_help')">
            <x-form.select name="accrual_method" :label="__('leave.fields.accrual_method')" required>
                @foreach ($methods as $method)
                    <option value="{{ $method->value }}" @selected(old('accrual_method', $record->accrual_method?->value) === $method->value)>{{ $method->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input type="number" name="entitlement_days" step="0.01" min="0" max="366" :label="__('leave.fields.entitlement_days')" :value="old('entitlement_days', $record->entitlement_days)" required :help="__('leave.help.entitlement_days')" />
            <x-form.field :label="__('leave.fields.service_tiers')" :message="$errors->first('service_tiers') ?: collect($errors->get('service_tiers.*'))->flatten()->first()" :help="__('leave.help.service_tiers')" class="md:col-span-2">
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($tiers as $index => $tier)
                        <div class="flex items-center gap-2">
                            <x-form.input type="number" name="service_tiers[{{ $index }}][min_years]" :label="__('leave.fields.min_years')" min="0" max="60" :value="$tier['min_years'] ?? ''" class="w-full" />
                            <x-form.input type="number" name="service_tiers[{{ $index }}][days]" :label="__('leave.fields.tier_days')" step="0.01" min="0" max="366" :value="$tier['days'] ?? ''" class="w-full" />
                        </div>
                    @endforeach
                </div>
            </x-form.field>
        </x-form.section>

        <x-form.section :title="__('leave.policies.carry_and_limits')" :description="__('leave.policies.carry_and_limits_help')">
            <x-form.input type="number" name="carry_over_max_days" step="0.5" min="0" max="366" :label="__('leave.fields.carry_over_max_days')" :value="old('carry_over_max_days', $record->carry_over_max_days)" :help="__('leave.help.carry_over_max_days')" />
            <x-form.input type="number" name="carry_over_expiry_months" min="1" max="24" :label="__('leave.fields.carry_over_expiry_months')" :value="old('carry_over_expiry_months', $record->carry_over_expiry_months)" :help="__('leave.help.carry_over_expiry_months')" />
            <x-form.input type="number" name="max_balance_days" step="0.5" min="0.5" max="999" :label="__('leave.fields.max_balance_days')" :value="old('max_balance_days', $record->max_balance_days)" :help="__('leave.help.empty_unlimited')" />
            <div class="hidden md:block" aria-hidden="true"></div>
            <div>
                <input type="hidden" name="allow_negative_balance" value="0">
                <x-form.switch name="allow_negative_balance" :label="__('leave.fields.allow_negative_balance')" :checked="(bool) old('allow_negative_balance', $record->allow_negative_balance)" />
            </div>
            <x-form.input type="number" name="negative_limit_days" step="0.5" min="0" max="366" :label="__('leave.fields.negative_limit_days')" :value="old('negative_limit_days', $record->negative_limit_days)" :help="__('leave.help.empty_unlimited')" />
            <div>
                <input type="hidden" name="encashable" value="0">
                <x-form.switch name="encashable" :label="__('leave.fields.encashable')" :description="__('leave.help.encashable')" :checked="(bool) old('encashable', $record->encashable)" />
            </div>
            <x-form.input type="number" name="encashment_max_days" step="0.5" min="0" max="366" :label="__('leave.fields.encashment_max_days')" :value="old('encashment_max_days', $record->encashment_max_days)" :help="__('leave.help.empty_unlimited')" />
            @include('admin.structure._description-field')
            @include('admin.structure._active-switch')
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.leave-policies.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
