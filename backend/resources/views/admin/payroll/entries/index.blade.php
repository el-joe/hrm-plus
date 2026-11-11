@extends('layouts.admin', ['subtitle' => __('payroll.entries.'.$resource.'.subtitle')])

@section('title', __('payroll.entries.'.$resource.'.title'))

@section('page-actions')
    @can('export', [App\Models\PayEntry::class, $kind])
        <x-button variant="ghost" icon="download" :href="route('admin.'.$resource.'.export', request()->query())">{{ __('payroll.common.export_xlsx') }}</x-button>
    @endcan
    @can('create', [App\Models\PayEntry::class, $kind])
        <x-button variant="secondary" icon="upload" :href="route('admin.'.$resource.'.import')">{{ __('payroll.entries.'.$resource.'.import') }}</x-button>
        <x-button variant="secondary" icon="users" :href="route('admin.'.$resource.'.bulk')">{{ __('payroll.entries.'.$resource.'.bulk') }}</x-button>
        <x-button icon="plus" :href="route('admin.'.$resource.'.create')">{{ __('payroll.entries.'.$resource.'.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.'.$resource.'.index')" :reset="route('admin.'.$resource.'.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="salary_component_id" :label="__('payroll.fields.salary_component_id')" hide-label class="md:w-52">
            <option value="">{{ __('payroll.fields.salary_component_id') }}: {{ __('app.common.all') }}</option>
            @foreach ($components as $component)
                <option value="{{ $component->id }}" @selected((string) ($filters['salary_component_id'] ?? '') === (string) $component->id)>{{ $component->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" :label="__('payroll.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('payroll.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="recurrence" :label="__('payroll.fields.recurrence')" hide-label class="md:w-48">
            <option value="">{{ __('payroll.fields.recurrence') }}: {{ __('app.common.all') }}</option>
            @foreach ($recurrences as $recurrence)
                <option value="{{ $recurrence->value }}" @selected(($filters['recurrence'] ?? '') === $recurrence->value)>{{ $recurrence->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="target_type" :label="__('payroll.fields.target_type')" hide-label class="md:w-44">
            <option value="">{{ __('payroll.fields.target_type') }}: {{ __('app.common.all') }}</option>
            @foreach ($targets as $target)
                <option value="{{ $target->value }}" @selected(($filters['target_type'] ?? '') === $target->value)>{{ $target->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.checkbox name="current" value="1" :label="__('payroll.entries.filter_current')" :checked="(bool) ($filters['current'] ?? false)" />
        @if ($filters['batch'] ?? null)
            <input type="hidden" name="batch" value="{{ $filters['batch'] }}">
            <x-badge tone="info">{{ __('payroll.entries.batch_filter', ['id' => $filters['batch']]) }}</x-badge>
        @endif
    </x-filter-bar>

    <x-table :caption="__('payroll.entries.'.$resource.'.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('payroll.fields.reference') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('payroll.entries.who') }}</x-table.th>
                <x-table.th>{{ __('payroll.fields.salary_component_id') }}</x-table.th>
                <x-table.th>{{ __('payroll.entries.value') }}</x-table.th>
                <x-table.th>{{ __('payroll.entries.when') }}</x-table.th>
                <x-table.th>{{ __('payroll.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($entries as $entry)
            <x-table.row :data-href="route('admin.'.$resource.'.show', $entry)">
                <x-table.td :label="__('payroll.fields.reference')" class="whitespace-nowrap">
                    <a href="{{ route('admin.'.$resource.'.show', $entry) }}" class="text-label-md text-text-link hover:underline" dir="ltr">{{ $entry->reference }}</a>
                </x-table.td>
                <x-table.td :label="__('payroll.entries.who')">{{ $entry->targetLabel() }}</x-table.td>
                <x-table.td :label="__('payroll.fields.salary_component_id')">{{ $entry->component->name }}</x-table.td>
                <x-table.td :label="__('payroll.entries.value')">@include('admin.payroll.entries._value')</x-table.td>
                <x-table.td :label="__('payroll.entries.when')" class="text-body-sm">
                    {{ $entry->recurrence->label() }}
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $entry->start_date->toDateString() }}@if ($entry->recurrence !== App\Enums\PayEntryRecurrence::OneTime) → {{ $entry->end_date?->toDateString() ?? '…' }}@endif</span>
                </x-table.td>
                <x-table.td :label="__('payroll.fields.status')">
                    @include('admin.payroll._request-status', ['status' => $entry->status])
                    @if ($entry->isPending() && $entry->latestApprovalRequest?->currentStep)
                        <span class="block text-caption-md text-text-tertiary">{{ __('payroll.common.at_step', ['step' => $entry->latestApprovalRequest->currentStep->name]) }}</span>
                    @endif
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" :icon="$kind === App\Enums\PayComponentKind::Deduction ? 'minus-circle' : 'plus-circle'" :title="__('payroll.entries.empty')" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$entries" />
        </x-slot:footer>
    </x-table>
@endsection
