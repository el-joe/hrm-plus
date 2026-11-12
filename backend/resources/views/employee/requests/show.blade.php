@extends('layouts.employee', ['subtitle' => $hrRequest->category->name.' · '.$hrRequest->reference])

@section('title', $hrRequest->subject)

@php
    $tz = setting('localisation.timezone');
    $letter = $hrRequest->employeeAttachments->where('is_letter', true)->last();
@endphp

@section('page-actions')
    <x-button variant="secondary" icon="arrow-left" flip :href="route('portal.requests.index')">{{ __('hr_requests.portal.title') }}</x-button>
@endsection

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

    @if ($hrRequest->status === App\Enums\HrRequestStatus::AwaitingEmployee)
        <x-alert tone="warning">{{ __('hr_requests.portal.waiting_for_you') }}</x-alert>
    @elseif ($hrRequest->status === App\Enums\HrRequestStatus::PendingApproval)
        <x-alert tone="info">{{ __('hr_requests.portal.pending_approval_note') }}</x-alert>
    @endif

    @if ($letter)
        <x-alert tone="success" :title="__('hr_requests.portal.letter_ready')">
            <a class="inline-flex items-center gap-1 text-text-link hover:underline" href="{{ route('portal.requests.attachments.download', [$hrRequest->id, $letter->id]) }}">
                <x-icon name="download" size="sm" /> {{ __('hr_requests.letters.download') }}
            </a>
        </x-alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex min-w-0 flex-col gap-4 lg:col-span-2">
            <x-card :title="__('hr_requests.show.details')" data-region="request-summary">
                <x-slot:actions>@include('admin.hr-requests._status', ['status' => $hrRequest->status])</x-slot:actions>
                <x-detail-list>
                    <x-detail-item :label="__('hr_requests.fields.reference')"><span dir="ltr">{{ $hrRequest->reference }}</span></x-detail-item>
                    <x-detail-item :label="__('hr_requests.fields.category')">{{ $hrRequest->category->name }}</x-detail-item>
                    <x-detail-item :label="__('hr_requests.fields.priority')">{{ $hrRequest->priority->label() }}</x-detail-item>
                    <x-detail-item :label="__('hr_requests.fields.submitted_at')"><x-date :value="$hrRequest->created_at" format="datetime" :timezone="$tz" /></x-detail-item>
                    @if ($hrRequest->letter_language)
                        <x-detail-item :label="__('hr_requests.fields.letter_language')">{{ __('app.locales.'.$hrRequest->letter_language) }}</x-detail-item>
                    @endif
                </x-detail-list>
                @if ($hrRequest->description)
                    <p class="mt-4 whitespace-pre-line text-body-md text-text-primary">{{ $hrRequest->description }}</p>
                @endif
                @if ($hrRequest->fieldValues->isNotEmpty())
                    <dl class="mt-4 grid gap-x-4 gap-y-1 text-body-sm sm:grid-cols-2">
                        @foreach ($hrRequest->fieldValues as $value)
                            <div class="flex gap-1"><dt class="text-text-tertiary">{{ $value->label() }}:</dt><dd>{{ $value->value }}</dd></div>
                        @endforeach
                    </dl>
                @endif
                @if ($hrRequest->rejection_reason)
                    <x-alert tone="danger" class="mt-4">{{ __('hr_requests.fields.rejection_reason') }}: {{ $hrRequest->rejection_reason }}</x-alert>
                @endif
            </x-card>

            @if ($hrRequest->profileChanges->isNotEmpty())
                <x-card :title="__('hr_requests.profile_changes.title')" padding="none">
                    <ul class="divide-y divide-border-subtle">
                        @foreach ($hrRequest->profileChanges as $change)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                                <span class="min-w-0">
                                    <span class="block text-label-md">{{ $fields->label($change->field) }}</span>
                                    <span class="block text-body-sm text-text-secondary" dir="auto">{{ $fields->display($change->field, $change->old_value) }} → {{ $fields->display($change->field, $change->new_value) }}</span>
                                </span>
                                <x-badge :tone="$change->status->tone()">{{ $change->status->label() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card :title="__('hr_requests.portal.conversation')" data-region="conversation">
                <ol class="flex flex-col gap-3">
                    @forelse ($hrRequest->employeeMessages as $message)
                        <li @class([
                            'rounded-lg border p-3',
                            'ms-6 border-border-subtle bg-action-primary-subtle' => $message->from_employee,
                            'me-6 border-border-subtle bg-surface-muted' => ! $message->from_employee,
                        ])>
                            <p class="text-caption-md text-text-tertiary">
                                <span class="text-label-sm text-text-primary">{{ $message->from_employee ? __('hr_requests.portal.you') : ($message->author?->name ?? __('hr_requests.portal.hr_team')) }}</span>
                                · <x-date :value="$message->created_at" format="datetime" :timezone="$tz" />
                            </p>
                            <p class="mt-1 whitespace-pre-line text-body-sm text-text-primary">{{ $message->body }}</p>
                        </li>
                    @empty
                        <li class="text-body-sm text-text-tertiary">{{ __('hr_requests.show.no_messages') }}</li>
                    @endforelse
                </ol>

                @unless ($hrRequest->isClosed())
                    <form method="POST" action="{{ route('portal.requests.reply', $hrRequest->id) }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-3 border-t border-border-subtle pt-4" data-validate>
                        @csrf
                        <x-form.textarea name="body" :label="__('hr_requests.portal.reply')" rows="3" required minlength="2" maxlength="5000">{{ old('body') }}</x-form.textarea>
                        <x-form.file-input name="attachments[]" :label="__('hr_requests.fields.attachments')" multiple :error-keys="['attachments', 'attachments.*']" />
                        <div class="flex justify-end"><x-button icon="send">{{ __('hr_requests.portal.send') }}</x-button></div>
                    </form>
                @endunless
            </x-card>
        </div>

        <div class="flex min-w-0 flex-col gap-4">
            <x-card :title="__('hr_requests.portal.files')" data-region="files">
                <ul class="flex flex-col gap-2">
                    @forelse ($hrRequest->employeeAttachments as $file)
                        <li>
                            <a class="inline-flex max-w-full items-center gap-2 text-body-sm text-text-link hover:underline" href="{{ route('portal.requests.attachments.download', [$hrRequest->id, $file->id]) }}">
                                <x-icon :name="$file->is_letter ? 'file-text' : 'paperclip'" size="sm" /> <span class="truncate">{{ $file->file_name }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="text-body-sm text-text-tertiary">{{ __('hr_requests.show.no_attachments') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :title="__('hr_requests.portal.status_history')" data-region="status-history">
                <ol class="flex flex-col gap-3 border-s border-border-default ps-4">
                    @foreach ($hrRequest->events as $event)
                        <li>
                            <p class="text-body-sm text-text-primary">{{ $event->description() }}</p>
                            <p class="text-caption-md text-text-tertiary"><x-date :value="$event->created_at" format="datetime" :timezone="$tz" /></p>
                        </li>
                    @endforeach
                </ol>
            </x-card>

            @if ($hrRequest->status->isCancellableByEmployee())
                <form method="POST" action="{{ route('portal.requests.cancel', $hrRequest->id) }}" data-confirm="{{ __('hr_requests.portal.confirm_cancel') }}" data-confirm-tone="warning">
                    @csrf
                    <x-button variant="ghost" icon="x" class="w-full">{{ __('hr_requests.portal.cancel') }}</x-button>
                </form>
            @endif
        </div>
    </div>
@endsection
