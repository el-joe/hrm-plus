@extends('layouts.admin', ['breadcrumbs' => [['label' => __('schedules.policies.title'), 'url' => route('admin.attendance-policies.index')]], 'subtitle' => $record->description])

@section('title', $record->name)

@section('page-actions')
    @can('update', $record)
        <x-button variant="secondary" icon="pencil" :href="route('admin.attendance-policies.edit', $record)">{{ __('app.actions.edit') }}</x-button>
        <x-button icon="plus" :href="route('admin.attendance-policies.versions.create', $record)">{{ __('schedules.policies.new_version') }}</x-button>
    @endcan
@endsection

@php
    $current = $record->versions->first(fn ($version) => $version->effective_from->format('Y-m-d') <= $today);
    $display = [App\Http\Controllers\Admin\Scheduling\AttendancePolicyController::class, 'display'];
@endphp

@section('content')
    <div class="flex flex-col gap-4 lg:gap-6">
        <x-card :title="__('schedules.policies.versions')" padding="none">
            <div class="overflow-x-auto">
                <table class="w-full min-w-160 text-body-sm">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle text-start">
                            <th class="px-4 py-2 text-start text-label-sm text-text-secondary">{{ __('schedules.policies.rules') }}</th>
                            @foreach ($record->versions as $version)
                                <th class="px-4 py-2 text-start text-label-sm text-text-secondary">
                                    {{ __('schedules.policies.version_n', ['n' => $version->version]) }}
                                    <span class="block font-normal" dir="ltr">{{ __('schedules.policies.from', ['date' => $version->effective_from->format('Y-m-d')]) }}</span>
                                    @if ($current?->is($version))
                                        <x-badge tone="success" dot>{{ __('schedules.policies.current') }}</x-badge>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sections as $section => $fields)
                            <tr><th colspan="{{ $record->versions->count() + 1 }}" class="bg-surface-subtle/50 px-4 pt-3 pb-1 text-start text-label-md text-text-primary">{{ __('schedules.policies.sections.'.$section) }}</th></tr>
                            @foreach ($fields as $field)
                                <tr class="border-b border-border-subtle">
                                    <td class="px-4 py-2 text-text-secondary">{{ __('schedules.policies.rule_labels.'.$field) }}</td>
                                    @foreach ($record->versions as $version)
                                        <td class="px-4 py-2 tabular-nums">{{ $display($version, $field) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card :title="__('schedules.policies.assignments')" :subtitle="__('schedules.policies.assignments_help')">
            <ul class="flex flex-col divide-y divide-border-subtle">
                @forelse ($record->assignments as $assignment)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span>
                            <span class="block text-label-md">{{ $assignment->scopeLabel() }}</span>
                            <span class="text-caption-md text-text-tertiary" dir="ltr">{{ $assignment->effective_from->format('Y-m-d') }} – {{ $assignment->effective_to?->format('Y-m-d') ?? '…' }}</span>
                        </span>
                        @can('update', $record)
                            <form method="POST" action="{{ route('admin.attendance-policies.assignments.destroy', [$record, $assignment]) }}"
                                data-confirm="{{ __('schedules.policies.confirm_remove_assignment') }}" data-confirm-label="{{ __('schedules.common.remove') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('schedules.common.remove')" />
                            </form>
                        @endcan
                    </li>
                @empty
                    <li class="py-2 text-body-sm text-text-tertiary">{{ __('schedules.policies.no_assignments') }}</li>
                @endforelse
            </ul>

            @can('update', $record)
                <form method="POST" action="{{ route('admin.attendance-policies.assignments.store', $record) }}" class="mt-4 grid gap-4 border-t border-border-subtle pt-4 md:grid-cols-2" data-scope-form>
                    @csrf
                    @include('admin.scheduling._scope-fields', ['currentType' => 'company', 'currentId' => null])
                    <x-form.input type="date" name="effective_from" :label="__('schedules.fields.effective_from')" :value="old('effective_from', $today)" required />
                    <x-form.input type="date" name="effective_to" :label="__('schedules.fields.effective_to')" :value="old('effective_to')" />
                    <div class="md:col-span-2">
                        <x-button icon="plus">{{ __('schedules.policies.add_assignment') }}</x-button>
                    </div>
                </form>
            @endcan
        </x-card>
    </div>
@endsection
