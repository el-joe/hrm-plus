@extends('layouts.admin', ['subtitle' => __('leave.policies.subtitle')])

@section('title', __('leave.policies.title'))

@section('page-actions')
    @can('create', App\Models\LeavePolicy::class)
        <x-button :href="route('admin.leave-policies.create', array_filter(['leave_type_id' => $filters['leave_type_id'] ?? null]))" icon="plus">{{ __('leave.policies.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.leave-policies.index')" :reset="route('admin.leave-policies.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="leave_type_id" :label="__('leave.fields.leave_type_id')" hide-label class="md:w-52">
            <option value="">{{ __('leave.fields.leave_type_id') }}: {{ __('app.common.all') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected((string) ($filters['leave_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" :label="__('leave.fields.status')" hide-label class="md:w-40">
            <option value="">{{ __('leave.fields.status') }}: {{ __('app.common.all') }}</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('structure.common.active') }}</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('structure.common.inactive') }}</option>
        </x-form.select>
        <x-form.checkbox name="current" value="1" :label="__('leave.policies.in_force_today')" :checked="(bool) ($filters['current'] ?? false)" />
    </x-filter-bar>

    <x-table :caption="__('leave.policies.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('leave.fields.name') }}</x-table.th>
                <x-table.th>{{ __('leave.fields.leave_type_id') }}</x-table.th>
                <x-table.th>{{ __('leave.fields.scope_type') }}</x-table.th>
                <x-table.th>{{ __('leave.policies.effective') }}</x-table.th>
                <x-table.th>{{ __('leave.fields.entitlement_days') }}</x-table.th>
                <x-table.th>{{ __('leave.policies.carry_and_limits') }}</x-table.th>
                <x-table.th>{{ __('leave.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            @php $inForce = $record->effective_from->format('Y-m-d') <= $today && ($record->effective_to === null || $record->effective_to->format('Y-m-d') >= $today); @endphp
            <x-table.row>
                <x-table.td :label="__('leave.fields.name')">
                    <span class="block text-label-md text-text-primary">{{ $record->name }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $record->code }}</span>
                </x-table.td>
                <x-table.td :label="__('leave.fields.leave_type_id')">
                    <span class="inline-flex items-center gap-2">@include('admin.leave._swatch', ['color' => $record->leaveType->color]) {{ $record->leaveType->name }}</span>
                </x-table.td>
                <x-table.td :label="__('leave.fields.scope_type')">{{ $record->scopeLabel() }}</x-table.td>
                <x-table.td :label="__('leave.policies.effective')" class="tabular-nums">
                    <span dir="ltr">{{ $record->effective_from->format('Y-m-d') }} → {{ $record->effective_to?->format('Y-m-d') ?? '…' }}</span>
                    @if ($inForce && $record->is_active)
                        <x-badge tone="success" class="ms-1">{{ __('leave.policies.in_force') }}</x-badge>
                    @endif
                </x-table.td>
                <x-table.td :label="__('leave.fields.entitlement_days')">
                    <span class="block tabular-nums">{{ $record->accrual_method->value === 'service_tiers' ? collect($record->tiers())->map(fn ($tier) => $tier['min_years'].'+: '.$tier['days'])->implode(' · ') : $record->entitlement_days }}</span>
                    <span class="block text-caption-md text-text-tertiary">{{ $record->accrual_method->label() }}</span>
                </x-table.td>
                <x-table.td :label="__('leave.policies.carry_and_limits')" class="text-caption-md text-text-secondary">
                    <span class="block">{{ __('leave.policies.carry_short', ['days' => $record->carry_over_max_days ?? '∞']) }}@if ($record->carry_over_expiry_months) · {{ __('leave.policies.expires_short', ['months' => $record->carry_over_expiry_months]) }}@endif</span>
                    @if ($record->max_balance_days)
                        <span class="block">{{ __('leave.policies.max_short', ['days' => $record->max_balance_days]) }}</span>
                    @endif
                    @if ($record->allow_negative_balance)
                        <span class="block">{{ __('leave.policies.negative_short', ['days' => $record->negative_limit_days ?? '∞']) }}</span>
                    @endif
                    @if ($record->encashable)
                        <span class="block">{{ __('leave.policies.encashable_short', ['days' => $record->encashment_max_days ?? '∞']) }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('leave.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>
                    <div class="flex items-center justify-end gap-1">
                        @can('create', App\Models\LeavePolicy::class)
                            <x-icon-button icon="history" size="sm" :href="route('admin.leave-policies.create', ['from' => $record->id])" :label="__('leave.policies.new_version')" />
                        @endcan
                        @include('admin.structure._row-actions', ['resource' => 'leave-policies'])
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="8" icon="shield-check" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
