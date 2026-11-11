@extends('layouts.admin', ['subtitle' => __('leave.types.subtitle')])

@section('title', __('leave.types.title'))

@section('page-actions')
    @can('create', App\Models\LeaveType::class)
        <x-button :href="route('admin.leave-types.create')" icon="plus">{{ __('leave.types.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.leave-types.index')" :reset="route('admin.leave-types.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="status" :label="__('leave.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('leave.fields.status') }}: {{ __('app.common.all') }}</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('structure.common.active') }}</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('structure.common.inactive') }}</option>
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('leave.types.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('leave.fields.name') }}</x-table.th>
                <x-table.th>{{ __('leave.fields.unit') }}</x-table.th>
                <x-table.th>{{ __('leave.types.pay') }}</x-table.th>
                <x-table.th>{{ __('leave.types.rules') }}</x-table.th>
                <x-table.th>{{ __('leave.fields.approval_workflow') }}</x-table.th>
                <x-table.th>{{ __('leave.policies.title') }}</x-table.th>
                <x-table.th>{{ __('leave.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('leave.fields.name')">
                    <span class="flex items-center gap-2 text-label-md text-text-primary">@include('admin.leave._swatch', ['color' => $record->color]) {{ $record->name }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $record->code }}</span>
                </x-table.td>
                <x-table.td :label="__('leave.fields.unit')">{{ $record->unit->label() }}</x-table.td>
                <x-table.td :label="__('leave.types.pay')">
                    <x-badge :tone="$record->is_paid ? 'success' : 'warning'">{{ $record->is_paid ? __('leave.types.paid') : __('leave.types.unpaid') }}</x-badge>
                    @if ($record->affects_payroll)
                        <x-badge tone="info">{{ __('leave.fields.affects_payroll') }}</x-badge>
                    @endif
                </x-table.td>
                <x-table.td :label="__('leave.types.rules')" class="text-caption-md text-text-secondary">
                    <span class="block">{{ $record->tracks_balance ? __('leave.types.tracks_balance_short') : __('leave.types.no_balance_short') }}</span>
                    @if ($record->gender)
                        <span class="block">{{ __('leave.fields.gender') }}: {{ $record->gender->label() }}</span>
                    @endif
                    @if ($record->min_service_months)
                        <span class="block">{{ __('leave.types.min_service_short', ['months' => $record->min_service_months]) }}</span>
                    @endif
                    @if ($record->requires_attachment)
                        <span class="block">{{ $record->attachment_after_days !== null ? __('leave.types.attachment_after_short', ['days' => $record->attachment_after_days]) : __('leave.types.attachment_always_short') }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('leave.fields.approval_workflow')"><span dir="ltr" class="text-caption-md">{{ $record->approval_workflow }}</span></x-table.td>
                <x-table.td :label="__('leave.policies.title')" class="tabular-nums">
                    @can('viewAny', App\Models\LeavePolicy::class)
                        <a class="text-text-link hover:underline" href="{{ route('admin.leave-policies.index', ['leave_type_id' => $record->id]) }}">{{ $record->policies_count }}</a>
                    @else
                        {{ $record->policies_count }}
                    @endcan
                </x-table.td>
                <x-table.td :label="__('leave.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'leave-types'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="8" icon="layers" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
