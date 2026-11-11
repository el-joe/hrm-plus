@extends('layouts.admin', ['breadcrumbs' => [['label' => __('leave.types.title'), 'url' => route('admin.leave-types.index')]]])

@section('title', $record->exists ? __('leave.types.edit') : __('leave.types.create'))

@php
    $switch = function (string $name, ?string $help = null) use ($record) {
        return ['name' => $name, 'checked' => (bool) old($name, $record->{$name}), 'help' => $help];
    };
@endphp

@section('content')
    <form method="POST" action="{{ $record->exists ? route('admin.leave-types.update', $record) : route('admin.leave-types.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('leave.types.identity')">
            @include('admin.structure._identity-fields')
            <x-form.select name="color" :label="__('leave.fields.color')" required>
                @foreach (App\Models\LeaveType::COLORS as $color)
                    <option value="{{ $color }}" @selected(old('color', $record->color) === $color)>{{ __('leave.colors.'.$color) }}</option>
                @endforeach
            </x-form.select>
            <x-form.input type="number" name="sort_order" :label="__('leave.fields.sort_order')" :value="old('sort_order', $record->sort_order)" min="0" max="9999" required />
            @include('admin.structure._description-field')
        </x-form.section>

        <x-form.section :title="__('leave.types.pay_and_balance')" :description="__('leave.types.pay_and_balance_help')">
            @foreach ([$switch('is_paid'), $switch('affects_payroll', __('leave.help.affects_payroll')), $switch('tracks_balance', __('leave.help.tracks_balance'))] as $item)
                <div class="md:col-span-2">
                    <input type="hidden" name="{{ $item['name'] }}" value="0">
                    <x-form.switch :name="$item['name']" :label="__('leave.fields.'.$item['name'])" :description="$item['help']" :checked="$item['checked']" />
                </div>
            @endforeach
        </x-form.section>

        <x-form.section :title="__('leave.types.request_rules')" :description="__('leave.types.request_rules_help')">
            <x-form.select name="unit" :label="__('leave.fields.unit')" required>
                @foreach ($units as $unit)
                    <option value="{{ $unit->value }}" @selected(old('unit', $record->unit?->value) === $unit->value)>{{ $unit->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input type="number" name="max_days_per_request" step="0.5" min="0.5" max="366" :label="__('leave.fields.max_days_per_request')" :value="old('max_days_per_request', $record->max_days_per_request)" :help="__('leave.help.empty_unlimited')" />
            <x-form.input type="number" name="notice_days" min="0" max="365" :label="__('leave.fields.notice_days')" :value="old('notice_days', $record->notice_days)" required />
            <div>
                <input type="hidden" name="allows_past_dates" value="0">
                <x-form.switch name="allows_past_dates" :label="__('leave.fields.allows_past_dates')" :description="__('leave.help.allows_past_dates')" :checked="(bool) old('allows_past_dates', $record->allows_past_dates)" />
            </div>
            <div>
                <input type="hidden" name="counts_rest_days" value="0">
                <x-form.switch name="counts_rest_days" :label="__('leave.fields.counts_rest_days')" :checked="(bool) old('counts_rest_days', $record->counts_rest_days)" />
            </div>
            <div>
                <input type="hidden" name="counts_holidays" value="0">
                <x-form.switch name="counts_holidays" :label="__('leave.fields.counts_holidays')" :checked="(bool) old('counts_holidays', $record->counts_holidays)" />
            </div>
            <div>
                <input type="hidden" name="requires_attachment" value="0">
                <x-form.switch name="requires_attachment" :label="__('leave.fields.requires_attachment')" :checked="(bool) old('requires_attachment', $record->requires_attachment)" />
            </div>
            <x-form.input type="number" name="attachment_after_days" step="0.5" min="0" max="365" :label="__('leave.fields.attachment_after_days')" :value="old('attachment_after_days', $record->attachment_after_days)" :help="__('leave.help.attachment_after_days')" />
        </x-form.section>

        <x-form.section :title="__('leave.types.eligibility')" :description="__('leave.types.eligibility_help')">
            <x-form.select name="gender" :label="__('leave.fields.gender')">
                <option value="">{{ __('leave.types.everyone') }}</option>
                @foreach ($genders as $gender)
                    <option value="{{ $gender->value }}" @selected(old('gender', $record->gender?->value) === $gender->value)>{{ $gender->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input type="number" name="min_service_months" min="0" max="600" :label="__('leave.fields.min_service_months')" :value="old('min_service_months', $record->min_service_months)" required />
            <x-form.input type="number" name="max_times_per_service" min="1" max="100" :label="__('leave.fields.max_times_per_service')" :value="old('max_times_per_service', $record->max_times_per_service)" :help="__('leave.help.empty_unlimited')" />
        </x-form.section>

        <x-form.section :title="__('leave.types.approval_and_attendance')" :description="__('leave.types.approval_and_attendance_help')">
            <x-form.select name="approval_workflow" :label="__('leave.fields.approval_workflow')" required>
                @foreach ($workflows as $workflow)
                    <option value="{{ $workflow->key }}" @selected(old('approval_workflow', $record->approval_workflow) === $workflow->key)>{{ $workflow->name }} ({{ trans_choice('leave.types.levels', $workflow->steps_count, ['count' => $workflow->steps_count]) }})</option>
                @endforeach
            </x-form.select>
            <x-form.select name="attendance_status_id" :label="__('leave.fields.attendance_status_id')">
                <option value="">{{ __('leave.types.generic_on_leave') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->id }}" @selected((string) old('attendance_status_id', $record->attendance_status_id) === (string) $status->id)>{{ $status->name }}</option>
                @endforeach
            </x-form.select>
            @include('admin.structure._active-switch')
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.leave-types.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
