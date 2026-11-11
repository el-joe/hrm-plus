@extends('layouts.admin', ['subtitle' => __('onboarding.offboarding.subtitle')])

@section('title', __('onboarding.offboarding.title'))

@section('page-actions')
    @can('create', App\Models\OffboardingCase::class)
        <x-button :href="route('admin.offboarding.create')" icon="plus">{{ __('onboarding.offboarding.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.offboarding.index')" :reset="route('admin.offboarding.index')">
        <x-form.select name="status" id="filter-status" :label="__('app.common.status')" hide-label class="md:w-44">
            @foreach (App\Enums\OffboardingStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? 'open') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="type" id="filter-type" :label="__('onboarding.fields.type')" hide-label class="md:w-48">
            <option value="">{{ __('onboarding.fields.type') }}: {{ __('app.common.all') }}</option>
            @foreach (App\Enums\TerminationType::cases() as $type)
                <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('onboarding.offboarding.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-56">{{ __('onboarding.fields.employee_id') }}</x-table.th>
                <x-table.th>{{ __('onboarding.fields.type') }}</x-table.th>
                <x-table.th>{{ __('onboarding.fields.last_working_date') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('onboarding.offboarding.handover') }}</x-table.th>
                <x-table.th>{{ __('app.common.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($cases as $case)
            <x-table.row :data-href="route('admin.offboarding.show', $case)">
                <x-table.td :label="__('onboarding.fields.employee_id')">
                    <a href="{{ route('admin.offboarding.show', $case) }}" class="block text-label-md text-text-primary hover:text-text-link">{{ $case->employee->name }}</a>
                    <span class="text-caption-md text-text-tertiary"><span dir="ltr">{{ $case->employee->employee_number }}</span> · {{ $case->employee->department?->name }}</span>
                </x-table.td>
                <x-table.td :label="__('onboarding.fields.type')">
                    {{ $case->type->label() }}
                    @if ($case->source === App\Enums\OffboardingSource::ContractExpiry)
                        <span class="block text-caption-md text-text-tertiary">{{ $case->source->label() }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('onboarding.fields.last_working_date')" class="tabular-nums"><span dir="ltr">{{ $case->last_working_date->toDateString() }}</span></x-table.td>
                <x-table.td :label="__('onboarding.offboarding.handover')">@include('admin.lifecycle._progress', ['value' => $case->progress(), 'label' => __('onboarding.offboarding.handover')])</x-table.td>
                <x-table.td :label="__('app.common.status')">@include('admin.recruitment._badge', ['value' => $case->status])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="log-out" :title="__('onboarding.offboarding.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$cases" />
        </x-slot:footer>
    </x-table>
@endsection
