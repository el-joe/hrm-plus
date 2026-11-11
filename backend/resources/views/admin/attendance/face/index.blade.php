@extends('layouts.admin', ['subtitle' => __('face.admin.subtitle')])

@section('title', __('face.admin.title'))

@section('content')
    @if ($simulated)
        <x-alert tone="warning" :title="__('face.admin.simulated_title')">{{ __('face.admin.simulated_help') }}</x-alert>
    @endif
    <x-alert tone="info" :title="__('face.admin.privacy_title')">{{ __('face.admin.privacy_help') }}</x-alert>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-stat-card :label="__('face.admin.active_enrolments')" :value="(string) $activeCount" icon="camera" />
        <x-stat-card :label="__('face.admin.active_consents')" :value="(string) $consentCount" icon="shield-check" />
        <div class="flex flex-col gap-2 rounded-xl border border-border-subtle bg-surface-default p-5 shadow-xs">
            <span class="text-label-md text-text-secondary">{{ __('face.fields.provider') }}</span>
            <span class="text-label-lg">{{ __('face.providers.'.$provider) }}</span>
            @if ($simulated) @include('admin.attendance._simulated') @endif
        </div>
        <x-stat-card :label="__('face.admin.consent_version')" :value="$consentVersion" icon="file-text" />
    </div>

    <x-filter-bar :action="route('admin.attendance.face.index')" :reset="route('admin.attendance.face.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="__('attendance.common.search_employee')" :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="status" :label="__('attendance.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('attendance.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach (['active', 'deleted'] as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('face.enums.enrolment_statuses.'.$status) }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('face.admin.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('attendance.fields.employee') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.status') }}</x-table.th>
                <x-table.th>{{ __('face.fields.consent') }}</x-table.th>
                <x-table.th>{{ __('face.fields.enrolled_at') }}</x-table.th>
                <x-table.th>{{ __('face.fields.provider') }}</x-table.th>
                <x-table.th>{{ __('face.admin.last_30_days') }}</x-table.th>
                <x-table.th class="text-end min-w-56"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($enrolments as $enrolment)
            <x-table.row>
                <x-table.td :label="__('attendance.fields.employee')">
                    <span class="block text-label-md">{{ $enrolment->employee?->name }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $enrolment->employee?->employee_number }}</span>
                </x-table.td>
                <x-table.td :label="__('attendance.fields.status')">
                    <x-badge :tone="$enrolment->isActive() ? 'success' : 'neutral'">{{ $enrolment->status->label() }}</x-badge>
                    @if ($enrolment->deletion_reason)
                        <span class="block text-caption-md text-text-tertiary">{{ __('face.deletion_reasons.'.$enrolment->deletion_reason) }} · <span dir="ltr">{{ $enrolment->deleted_at?->format('Y-m-d') }}</span></span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('face.fields.consent')">
                    {{ __('face.admin.consent_version_n', ['version' => $enrolment->consent?->version]) }}
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $enrolment->consent?->given_at?->format('Y-m-d') }}</span>
                    @if ($enrolment->consent?->withdrawn_at)<x-badge tone="danger">{{ __('face.admin.withdrawn') }}</x-badge>@endif
                </x-table.td>
                <x-table.td :label="__('face.fields.enrolled_at')" class="tabular-nums"><span dir="ltr">{{ $enrolment->enrolled_at->format('Y-m-d H:i') }}</span></x-table.td>
                <x-table.td :label="__('face.fields.provider')">{{ $enrolment->providerLabel() }} @if ($enrolment->is_simulated) @include('admin.attendance._simulated') @endif</x-table.td>
                <x-table.td :label="__('face.admin.last_30_days')">
                    @foreach ($verifications->get($enrolment->employee_id, collect()) as $row)
                        <span class="block text-caption-md">{{ $row->result->label() }}: {{ $row->total }}</span>
                    @endforeach
                </x-table.td>
                <x-table.td>
                    @can('delete', $enrolment)
                        <form method="POST" action="{{ route('admin.attendance.face.destroy', $enrolment) }}" class="flex items-center justify-end gap-2"
                            data-confirm="{{ __('face.admin.confirm_delete') }}" data-confirm-tone="danger" data-confirm-label="{{ __('face.admin.delete') }}">
                            @csrf @method('DELETE')
                            <x-form.input name="reason" :id="'reason-'.$enrolment->id" :label="__('attendance.fields.reason')" hide-label required maxlength="500" :placeholder="__('attendance.fields.reason')" class="w-36" />
                            <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('face.admin.delete')" />
                        </form>
                    @endcan
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="camera" :title="__('face.admin.empty')" :description="__('face.admin.empty_help')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$enrolments" />
        </x-slot:footer>
    </x-table>
@endsection
