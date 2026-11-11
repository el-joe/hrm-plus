@extends('layouts.admin', ['subtitle' => __('mobile.locations.subtitle')])

@section('title', __('mobile.locations.title'))

@section('content')
    <x-alert tone="info" :title="__('mobile.locations.default_title')">{{ __('mobile.locations.default_help') }}</x-alert>

    <x-card :title="__('mobile.locations.add')" data-region="add-rule">
        <form method="POST" action="{{ route('admin.attendance.work-locations.store') }}" class="grid gap-4 md:grid-cols-3" data-validate>
            @csrf
            <x-form.select name="scope_type" :label="__('mobile.locations.fields.scope_type')" required>
                @foreach ($scopes as $scope)
                    <option value="{{ $scope->value }}" @selected(old('scope_type', 'employee') === $scope->value)>{{ $scope->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="employee_id" :label="__('mobile.locations.fields.employee_id')" :help="__('mobile.locations.employee_help')">
                <option value="">—</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="department_id" :label="__('mobile.locations.fields.department_id')" :help="__('mobile.locations.department_help')">
                <option value="">—</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->code }} · {{ $department->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="branch_location_id" :label="__('mobile.locations.fields.branch_location_id')">
                <option value="">—</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) old('branch_location_id') === (string) $location->id)>{{ $location->branch?->code }} · {{ $location->name }} ({{ $location->radius_meters }} m)</option>
                @endforeach
            </x-form.select>
            <div class="flex items-center">
                <input type="hidden" name="allow_anywhere" value="0">
                <x-form.switch name="allow_anywhere" :label="__('mobile.locations.fields.allow_anywhere')" :description="__('mobile.locations.anywhere_help')" :checked="(bool) old('allow_anywhere')" />
            </div>
            <x-form.input name="note" :label="__('mobile.locations.fields.note')" :value="old('note')" maxlength="500" />
            <x-form.input name="effective_from" type="date" :label="__('mobile.locations.fields.effective_from')" :value="old('effective_from')" />
            <x-form.input name="effective_to" type="date" :label="__('mobile.locations.fields.effective_to')" :value="old('effective_to')" />
            <div class="flex items-end"><x-button icon="plus">{{ __('mobile.locations.add') }}</x-button></div>
        </form>
    </x-card>

    <x-table :caption="__('mobile.locations.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('mobile.locations.fields.scope_type') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('mobile.locations.applies_to') }}</x-table.th>
                <x-table.th>{{ __('mobile.locations.fields.branch_location_id') }}</x-table.th>
                <x-table.th>{{ __('mobile.locations.period') }}</x-table.th>
                <x-table.th>{{ __('mobile.locations.fields.note') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($rules as $rule)
            <x-table.row>
                <x-table.td :label="__('mobile.locations.fields.scope_type')">{{ $rule->scope_type->label() }}</x-table.td>
                <x-table.td :label="__('mobile.locations.applies_to')">{{ $rule->scopeLabel() }}</x-table.td>
                <x-table.td :label="__('mobile.locations.fields.branch_location_id')">
                    @if ($rule->allow_anywhere)
                        <x-badge tone="info">{{ __('mobile.locations.anywhere') }}</x-badge>
                    @else
                        {{ $rule->location?->branch?->code }} · {{ $rule->location?->name }}
                    @endif
                </x-table.td>
                <x-table.td :label="__('mobile.locations.period')" class="tabular-nums"><span dir="ltr">{{ $rule->effective_from?->format('Y-m-d') ?? '…' }} – {{ $rule->effective_to?->format('Y-m-d') ?? '…' }}</span></x-table.td>
                <x-table.td :label="__('mobile.locations.fields.note')">{{ $rule->note ?? '—' }}</x-table.td>
                <x-table.td>
                    <form method="POST" action="{{ route('admin.attendance.work-locations.destroy', $rule) }}" class="flex justify-end" data-confirm="{{ __('mobile.locations.confirm_remove') }}" data-confirm-tone="warning">
                        @csrf @method('DELETE')
                        <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('schedules.common.remove')" />
                    </form>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="map-pin" :title="__('mobile.locations.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$rules" />
        </x-slot:footer>
    </x-table>
@endsection
