@extends('layouts.admin', ['subtitle' => __('training.certificates.subtitle')])

@section('title', __('training.certificates.title'))

@section('page-actions')
    @can('export', App\Models\TrainingCertificate::class)
        <x-button variant="ghost" icon="download" :href="route('admin.training-certificates.export', request()->query())">{{ __('training.common.export_xlsx') }}</x-button>
    @endcan
    @can('create', App\Models\TrainingCertificate::class)
        <x-button icon="plus" :href="route('admin.training-certificates.create', array_filter(['employee' => $filters['employee_id'] ?? null]))">{{ __('training.certificates.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <div class="grid gap-3 sm:grid-cols-3">
        <x-stat-card :label="__('training.certificates.expiring_within', ['days' => $reminderDays])" :value="$counts['expiring']" icon="alert-triangle" :href="route('admin.training-certificates.index', ['validity' => 'expiring'])" />
        <x-stat-card :label="__('training.validity.expired')" :value="$counts['expired']" icon="x-circle" :href="route('admin.training-certificates.index', ['validity' => 'expired'])" />
        <x-stat-card :label="__('training.certificates.total')" :value="$counts['total']" icon="award" />
    </div>

    <x-filter-bar :action="route('admin.training-certificates.index')" :reset="route('admin.training-certificates.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="type" :label="__('training.fields.type')" hide-label class="md:w-44">
            <option value="">{{ __('training.fields.type') }}: {{ __('app.common.all') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="validity" :label="__('training.fields.validity')" hide-label class="md:w-48">
            <option value="">{{ __('training.fields.validity') }}: {{ __('app.common.all') }}</option>
            @foreach (['expiring', 'expired', 'valid', 'no_expiry'] as $state)
                <option value="{{ $state }}" @selected(($filters['validity'] ?? '') === $state)>{{ __('training.validity.'.$state) }}</option>
            @endforeach
        </x-form.select>
        @if (! empty($filters['employee_id']))
            <input type="hidden" name="employee_id" value="{{ $filters['employee_id'] }}">
        @endif
    </x-filter-bar>

    <x-table :caption="__('training.certificates.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('training.fields.employee') }}</x-table.th>
                <x-table.th sort="name" class="min-w-48">{{ __('training.fields.name') }}</x-table.th>
                <x-table.th sort="issued_on">{{ __('training.fields.issued_on') }}</x-table.th>
                <x-table.th sort="expires_on">{{ __('training.fields.expires_on') }}</x-table.th>
                <x-table.th>{{ __('training.fields.validity') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($certificates as $certificate)
            <x-table.row>
                <x-table.td :label="__('training.fields.employee')">{{ $certificate->employee->label() }}</x-table.td>
                <x-table.td :label="__('training.fields.name')">
                    <span class="text-label-md text-text-primary">{{ $certificate->name }}</span>
                    <span class="block text-caption-md text-text-tertiary">{{ $certificate->type->label() }}@if ($certificate->issuer) · {{ $certificate->issuer }}@endif @if ($certificate->certificate_number) · <span dir="ltr">{{ $certificate->certificate_number }}</span>@endif</span>
                </x-table.td>
                <x-table.td :label="__('training.fields.issued_on')"><span dir="ltr">{{ $certificate->issued_on?->toDateString() ?? '—' }}</span></x-table.td>
                <x-table.td :label="__('training.fields.expires_on')"><span dir="ltr">{{ $certificate->expires_on?->toDateString() ?? '—' }}</span></x-table.td>
                <x-table.td :label="__('training.fields.validity')">@include('admin.training._validity')</x-table.td>
                <x-table.td>
                    <div class="flex items-center justify-end gap-1">
                        @if ($certificate->hasFile())
                            <x-icon-button icon="download" size="sm" :href="route('admin.training-certificates.download', $certificate)" :label="__('training.certificates.download')" />
                        @endif
                        @can('update', $certificate)
                            <x-icon-button icon="pencil" size="sm" :href="route('admin.training-certificates.edit', $certificate)" :label="__('app.actions.edit')" />
                        @endcan
                        @can('delete', $certificate)
                            <form method="POST" action="{{ route('admin.training-certificates.destroy', $certificate) }}" data-confirm="{{ __('training.certificates.delete_confirm', ['name' => $certificate->name]) }}" data-confirm-tone="danger">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('app.actions.delete')" />
                            </form>
                        @endcan
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="award" :title="__('training.certificates.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$certificates" />
        </x-slot:footer>
    </x-table>
@endsection
