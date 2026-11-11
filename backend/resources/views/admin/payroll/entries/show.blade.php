@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('payroll.entries.'.$resource.'.title'), 'url' => route('admin.'.$resource.'.index')]],
    'subtitle' => $target,
])

@section('title', $entry->reference)

@section('content')
    @if ($errors->any())
        <x-alert tone="danger">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-card :title="__('payroll.entries.'.$resource.'.singular')">
                <x-slot:actions>@include('admin.payroll._request-status', ['status' => $entry->status])</x-slot:actions>
                <x-detail-list>
                    <x-detail-item :label="__('payroll.entries.who')">{{ $target }}</x-detail-item>
                    <x-detail-item :label="__('payroll.fields.salary_component_id')">{{ $entry->component->name }} <span class="text-text-tertiary" dir="ltr">({{ $entry->component->code }})</span></x-detail-item>
                    <x-detail-item :label="__('payroll.entries.value')">@include('admin.payroll.entries._value')</x-detail-item>
                    <x-detail-item :label="__('payroll.fields.recurrence')">{{ $entry->recurrence->label() }}</x-detail-item>
                    <x-detail-item :label="__('payroll.fields.start_date')"><span dir="ltr">{{ $entry->start_date->toDateString() }}</span></x-detail-item>
                    <x-detail-item :label="__('payroll.fields.end_date')"><span dir="ltr">{{ $entry->end_date?->toDateString() ?? __('payroll.common.open_ended') }}</span></x-detail-item>
                    <x-detail-item :label="__('payroll.entries.treatment')">
                        {{ __('payroll.fields.is_taxable') }}: {{ ($entry->is_taxable ?? $entry->component->is_taxable) ? __('payroll.common.yes') : __('payroll.common.no') }} ·
                        {{ __('payroll.fields.is_insurable') }}: {{ ($entry->is_insurable ?? $entry->component->is_insurable) ? __('payroll.common.yes') : __('payroll.common.no') }}
                        @if ($entry->recurrence === App\Enums\PayEntryRecurrence::Recurring)
                            · {{ __('payroll.fields.prorate') }}: {{ ($entry->prorate ?? $entry->component->prorate_partial_period) ? __('payroll.common.yes') : __('payroll.common.no') }}
                        @endif
                    </x-detail-item>
                    <x-detail-item :label="__('payroll.fields.reason')">{{ $entry->reason ?? '—' }}</x-detail-item>
                    @if ($entry->notes)
                        <x-detail-item :label="__('payroll.fields.notes')">{{ $entry->notes }}</x-detail-item>
                    @endif
                    <x-detail-item :label="__('payroll.fields.created_by')">{{ $entry->creator?->name ?? '—' }} · <span dir="ltr">{{ $entry->created_at?->format('Y-m-d H:i') }}</span></x-detail-item>
                    @if ($entry->batch)
                        <x-detail-item :label="__('payroll.entries.source')">{{ __('payroll.entries.from_batch', ['id' => $entry->batch->id, 'source' => __('payroll.entries.batch_sources.'.$entry->batch->source)]) }}</x-detail-item>
                    @endif
                    @if ($entry->rejection_reason)
                        <x-detail-item :label="__('payroll.common.reject_reason')">{{ $entry->rejection_reason }}</x-detail-item>
                    @endif
                    @if ($entry->cancellation_reason)
                        <x-detail-item :label="__('payroll.fields.reason')">{{ $entry->cancellation_reason }}</x-detail-item>
                    @endif
                </x-detail-list>
            </x-card>

            <x-card :title="__('payroll.common.attachments')" data-region="attachments">
                <ul class="flex flex-col gap-2">
                    @forelse ($entry->attachments as $file)
                        <li><a class="inline-flex items-center gap-1 text-text-link hover:underline" href="{{ route('admin.'.$resource.'.attachments.download', [$entry, $file]) }}"><x-icon name="file-text" size="sm" />{{ $file->file_name }}</a>
                            <span class="text-caption-md text-text-tertiary">· {{ $file->uploader?->name }}</span></li>
                    @empty
                        <li class="text-body-sm text-text-tertiary">{{ __('payroll.common.no_attachments') }}</li>
                    @endforelse
                </ul>
                @can('addAttachments', $entry)
                    <form method="POST" action="{{ route('admin.'.$resource.'.attachments.store', $entry) }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end" data-validate>
                        @csrf
                        <x-form.file-input name="attachments[]" id="more-attachments" :label="__('payroll.common.add_attachments')" multiple required :error-keys="['attachments', 'attachments.*']" />
                        <x-button variant="secondary" icon="upload">{{ __('payroll.common.add_attachments') }}</x-button>
                    </form>
                @endcan
            </x-card>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            @can('decide', $entry)
                <x-card :title="__('payroll.common.decide')" data-region="decision">
                    <form method="POST" action="{{ route('admin.'.$resource.'.decide', [$entry, 'approve']) }}" class="flex flex-col gap-2">
                        @csrf
                        <x-form.input name="reason" id="approve-reason" :label="__('payroll.common.comment')" maxlength="1000" />
                        <x-button icon="check">{{ __('payroll.common.approve') }}</x-button>
                    </form>
                    <form method="POST" action="{{ route('admin.'.$resource.'.decide', [$entry, 'reject']) }}" class="mt-4 flex flex-col gap-2" data-validate>
                        @csrf
                        <x-form.textarea name="reason" id="reject-reason" :label="__('payroll.common.reject_reason')" required maxlength="1000" rows="2" />
                        <x-button variant="danger" icon="x-circle">{{ __('payroll.common.reject') }}</x-button>
                    </form>
                </x-card>
            @endcan

            @can('stop', $entry)
                <x-card :title="__('payroll.entries.stop_title')" :subtitle="__('payroll.entries.stop_help')">
                    <form method="POST" action="{{ route('admin.'.$resource.'.stop', $entry) }}" class="flex flex-col gap-2" data-validate>
                        @csrf
                        <x-form.input type="date" name="end_date" required :label="__('payroll.fields.end_date')" :value="now()->endOfMonth()->toDateString()" />
                        <x-form.input name="reason" id="stop-reason" required maxlength="1000" :label="__('payroll.fields.reason')" />
                        <x-button variant="secondary" icon="power">{{ __('payroll.entries.stop') }}</x-button>
                    </form>
                </x-card>
            @endcan

            @can('cancel', $entry)
                <x-card :title="__('payroll.entries.cancel_title')">
                    <form method="POST" action="{{ route('admin.'.$resource.'.cancel', $entry) }}" class="flex flex-col gap-2" data-validate data-confirm="{{ __('payroll.entries.cancel_title') }}?" data-confirm-tone="danger">
                        @csrf
                        <x-form.input name="reason" id="cancel-reason" required maxlength="1000" :label="__('payroll.fields.reason')" />
                        <x-button variant="danger" icon="x">{{ __('payroll.entries.cancel') }}</x-button>
                    </form>
                </x-card>
            @endcan

            <x-card :title="__('payroll.common.approval_history')">
                @include('admin.payroll._approval-history', ['requests' => $entry->approvalRequests])
            </x-card>
        </div>
    </div>
@endsection
