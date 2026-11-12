@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('hr_requests.queue.title'), 'url' => route('admin.hr-requests.index')]],
    'subtitle' => $hrRequest->employee->label(),
])

@section('title', $hrRequest->reference.' · '.$hrRequest->subject)

@php
    $user = auth()->user();
    $tz = setting('localisation.timezone');
    $template = $hrRequest->category->letterTemplate;
    $pendingChanges = $hrRequest->profileChanges->where('status', App\Enums\HrProfileChangeStatus::Pending);
@endphp

@section('page-actions')
    @can('view', $hrRequest->employee)
        <x-button variant="secondary" icon="user" :href="route('admin.employees.show', $hrRequest->employee)">{{ __('hr_requests.fields.employee') }}</x-button>
    @endcan
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

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex min-w-0 flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-card :title="__('hr_requests.show.details')" data-region="request-details">
                <x-slot:actions>
                    <span class="flex flex-wrap gap-2">
                        @include('admin.hr-requests._priority', ['priority' => $hrRequest->priority])
                        @include('admin.hr-requests._status', ['status' => $hrRequest->status])
                    </span>
                </x-slot:actions>
                <x-detail-list>
                    <x-detail-item :label="__('hr_requests.fields.reference')"><span dir="ltr">{{ $hrRequest->reference }}</span></x-detail-item>
                    <x-detail-item :label="__('hr_requests.fields.category')">{{ $hrRequest->category->name }} <span class="block text-caption-md text-text-tertiary">{{ $hrRequest->category->type->label() }}</span></x-detail-item>
                    <x-detail-item :label="__('hr_requests.fields.employee')">
                        {{ $hrRequest->employee->label() }}
                        <span class="block text-caption-md text-text-tertiary">{{ $hrRequest->employee->jobTitle?->name }} · {{ $hrRequest->department?->name }}</span>
                    </x-detail-item>
                    <x-detail-item :label="__('hr_requests.fields.submitted_at')">
                        <span dir="ltr">{{ $hrRequest->created_at->timezone($tz)->format('Y-m-d H:i') }}</span>
                        <span class="block text-caption-md text-text-tertiary">{{ $hrRequest->source->label() }} · {{ $hrRequest->submitter?->name }}</span>
                    </x-detail-item>
                    @if ($hrRequest->letter_language)
                        <x-detail-item :label="__('hr_requests.fields.letter_language')">{{ __('app.locales.'.$hrRequest->letter_language) }}</x-detail-item>
                    @endif
                    @if ($hrRequest->closed_at)
                        <x-detail-item :label="__('hr_requests.fields.resolved_at')">{{ __('hr_requests.show.closed_by', ['name' => $hrRequest->closer?->name ?? '—', 'date' => $hrRequest->closed_at->timezone($tz)->format('Y-m-d H:i')]) }}</x-detail-item>
                    @endif
                </x-detail-list>
                @if ($hrRequest->description)
                    <h3 class="mt-4 text-label-md text-text-secondary">{{ __('hr_requests.fields.description') }}</h3>
                    <p class="mt-1 whitespace-pre-line text-body-md text-text-primary">{{ $hrRequest->description }}</p>
                @endif
                @if ($hrRequest->fieldValues->isNotEmpty())
                    <h3 class="mt-4 text-label-md text-text-secondary">{{ __('hr_requests.show.answers') }}</h3>
                    <dl class="mt-1 grid gap-x-4 gap-y-1 text-body-sm sm:grid-cols-2">
                        @foreach ($hrRequest->fieldValues as $value)
                            <div class="flex gap-1"><dt class="text-text-tertiary">{{ $value->label() }}:</dt><dd class="text-text-primary">{{ $value->value }}</dd></div>
                        @endforeach
                    </dl>
                @endif
                @if ($hrRequest->rejection_reason)
                    <x-alert tone="danger" class="mt-4">{{ __('hr_requests.fields.rejection_reason') }}: {{ $hrRequest->rejection_reason }}</x-alert>
                @endif
            </x-card>

            @if ($hrRequest->profileChanges->isNotEmpty())
                <x-card :title="__('hr_requests.profile_changes.title')" padding="none" data-region="profile-changes">
                    @if (in_array(false, $reveal, true) && $hrRequest->profileChanges->contains(fn ($change) => $fields->isMasked($change->field)))
                        <div class="px-4 pt-4"><x-alert tone="info">{{ __('hr_requests.profile_changes.masked_notice') }}</x-alert></div>
                    @endif
                    <x-table :caption="__('hr_requests.profile_changes.title')">
                        <x-slot:head>
                            <tr>
                                <x-table.th>{{ __('hr_requests.profile_changes.field') }}</x-table.th>
                                <x-table.th>{{ __('hr_requests.profile_changes.value_then') }}</x-table.th>
                                <x-table.th>{{ __('hr_requests.profile_changes.requested') }}</x-table.th>
                                <x-table.th>{{ __('hr_requests.fields.status') }}</x-table.th>
                            </tr>
                        </x-slot:head>
                        @foreach ($hrRequest->profileChanges as $change)
                            @php($show = ! $fields->isMasked($change->field) || $reveal[$fields->group($change->field)])
                            <x-table.row>
                                <x-table.td :label="__('hr_requests.profile_changes.field')" class="text-label-md">{{ $fields->label($change->field) }}</x-table.td>
                                <x-table.td :label="__('hr_requests.profile_changes.value_then')"><span dir="auto" class="text-text-secondary">{{ $fields->display($change->field, $change->old_value, $show) }}</span></x-table.td>
                                <x-table.td :label="__('hr_requests.profile_changes.requested')"><span dir="auto" class="text-label-md text-text-primary">{{ $fields->display($change->field, $change->new_value, $show) }}</span></x-table.td>
                                <x-table.td :label="__('hr_requests.fields.status')"><x-badge :tone="$change->status->tone()">{{ $change->status->label() }}</x-badge></x-table.td>
                            </x-table.row>
                        @endforeach
                    </x-table>
                    @if ($pendingChanges->isNotEmpty())
                        @can('decideChanges', $hrRequest)
                            <div class="flex flex-col gap-3 border-t border-border-subtle p-4 sm:flex-row sm:items-end" data-region="profile-change-decision">
                                <form method="POST" action="{{ route('admin.hr-requests.changes.apply', $hrRequest) }}" class="flex flex-1 flex-col gap-2"
                                    data-confirm="{{ __('hr_requests.profile_changes.confirm_apply') }}">
                                    @csrf
                                    <p class="text-caption-md text-text-tertiary">{{ __('hr_requests.profile_changes.apply_hint') }}</p>
                                    <x-button icon="check">{{ __('hr_requests.profile_changes.apply') }}</x-button>
                                </form>
                                <form method="POST" action="{{ route('admin.hr-requests.changes.reject', $hrRequest) }}" class="flex flex-1 flex-col gap-2" data-validate>
                                    @csrf
                                    <x-form.input name="rejection_reason" id="changes-reject-reason" :label="__('hr_requests.fields.rejection_reason')" required maxlength="1000" />
                                    <x-button variant="danger" icon="x">{{ __('hr_requests.profile_changes.reject') }}</x-button>
                                </form>
                            </div>
                        @endcan
                    @endif
                </x-card>
            @endif

            <x-card :title="__('hr_requests.show.thread')" :subtitle="__('hr_requests.show.thread_hint')" data-region="thread">
                <ol class="flex flex-col gap-3">
                    @forelse ($hrRequest->messages as $message)
                        <li @class([
                            'rounded-lg border p-3',
                            'border-feedback-warning-border bg-feedback-warning-bg' => $message->isInternal(),
                            'border-border-subtle bg-surface-muted' => $message->type === App\Enums\HrRequestMessageType::System,
                            'border-border-subtle bg-surface-default' => $message->type === App\Enums\HrRequestMessageType::Reply,
                        ]) data-message-type="{{ $message->type->value }}">
                            <div class="flex flex-wrap items-center gap-2 text-caption-md text-text-tertiary">
                                <span class="text-label-md text-text-primary">{{ $message->from_employee ? __('hr_requests.show.from_employee').' · '.$hrRequest->employee->name : ($message->author?->name ?? __('hr_requests.show.hr')) }}</span>
                                @if ($message->isInternal())
                                    <x-badge tone="warning"><x-icon name="lock" size="sm" /> {{ __('hr_requests.enums.message_types.internal_note') }}</x-badge>
                                @elseif ($message->type === App\Enums\HrRequestMessageType::System)
                                    <x-badge>{{ __('hr_requests.enums.message_types.system') }}</x-badge>
                                @endif
                                <span dir="ltr">{{ $message->created_at->timezone($tz)->format('Y-m-d H:i') }}</span>
                            </div>
                            <p class="mt-1 whitespace-pre-line text-body-sm text-text-primary">{{ $message->body }}</p>
                            @foreach ($hrRequest->attachments->where('hr_request_message_id', $message->id) as $file)
                                <a class="mt-1 inline-flex items-center gap-1 text-caption-md text-text-link hover:underline" href="{{ route('admin.hr-requests.attachments.download', [$hrRequest, $file]) }}">
                                    <x-icon name="paperclip" size="sm" /> {{ $file->file_name }}
                                </a>
                            @endforeach
                        </li>
                    @empty
                        <li class="text-body-sm text-text-tertiary">{{ __('hr_requests.show.no_messages') }}</li>
                    @endforelse
                </ol>

                @if ($canUpdate)
                    <form method="POST" action="{{ route('admin.hr-requests.messages.store', $hrRequest) }}" enctype="multipart/form-data"
                        class="mt-4 flex flex-col gap-3 border-t border-border-subtle pt-4" data-validate data-region="reply-form">
                        @csrf
                        <fieldset class="flex flex-wrap gap-x-5 gap-y-2">
                            <legend class="sr-only">{{ __('hr_requests.show.thread') }}</legend>
                            <x-form.radio name="visibility" value="reply" :label="__('hr_requests.show.reply')" :checked="old('visibility', $hrRequest->status->isHandled() ? 'reply' : 'internal') === 'reply'" :disabled="! $hrRequest->status->isHandled()" />
                            <x-form.radio name="visibility" value="internal" :label="__('hr_requests.show.internal_note')" :checked="old('visibility', $hrRequest->status->isHandled() ? 'reply' : 'internal') === 'internal'" />
                        </fieldset>
                        <x-form.textarea name="body" :label="__('hr_requests.fields.body')" hide-label rows="4" required minlength="2" maxlength="10000">{{ old('body') }}</x-form.textarea>
                        @if ($hrRequest->status->isHandled())
                            <x-form.checkbox name="await_employee" value="1" :label="__('hr_requests.show.await_employee')" :checked="(bool) old('await_employee')" />
                        @endif
                        <x-form.file-input name="attachments[]" :label="__('hr_requests.fields.attachments')" multiple :error-keys="['attachments', 'attachments.*']" />
                        <div class="flex justify-end"><x-button icon="send">{{ __('hr_requests.show.send') }}</x-button></div>
                    </form>
                @endif
            </x-card>

            <x-card :title="__('hr_requests.show.attachments')" data-region="attachments">
                <ul class="flex flex-col divide-y divide-border-subtle">
                    @forelse ($hrRequest->attachments as $file)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <a class="inline-flex min-w-0 items-center gap-2 text-body-sm text-text-link hover:underline" href="{{ route('admin.hr-requests.attachments.download', [$hrRequest, $file]) }}">
                                <x-icon name="{{ $file->is_letter ? 'file-text' : 'paperclip' }}" size="sm" /> <span class="truncate">{{ $file->file_name }}</span>
                            </a>
                            <span class="flex items-center gap-2 text-caption-md text-text-tertiary">
                                @if ($file->is_letter) <x-badge tone="success">{{ __('hr_requests.common.letter') }}</x-badge> @endif
                                @if ($file->is_internal) <x-badge tone="warning">{{ __('hr_requests.common.internal') }}</x-badge> @endif
                                {{ $file->uploader?->name }} · <span dir="ltr">{{ number_format($file->file_size / 1024, 0) }} KB</span>
                            </span>
                        </li>
                    @empty
                        <li class="py-2 text-body-sm text-text-tertiary">{{ __('hr_requests.show.no_attachments') }}</li>
                    @endforelse
                </ul>
                @if ($canUpdate)
                    <form method="POST" action="{{ route('admin.hr-requests.attachments.store', $hrRequest) }}" enctype="multipart/form-data" class="mt-3 flex flex-col gap-2 border-t border-border-subtle pt-3">
                        @csrf
                        <x-form.file-input name="attachments[]" id="extra-attachments" :label="__('hr_requests.show.add_attachments')" multiple required :error-keys="['attachments', 'attachments.*']" />
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <x-form.checkbox name="internal" value="1" :label="__('hr_requests.show.internal_files')" />
                            <x-button variant="secondary" size="sm" icon="upload">{{ __('hr_requests.show.upload') }}</x-button>
                        </div>
                    </form>
                @endif
            </x-card>
        </div>

        <div class="flex min-w-0 flex-col gap-4 lg:gap-6">
            <x-card :title="__('hr_requests.show.assignment')" data-region="assignment">
                <x-detail-list>
                    <x-detail-item :label="__('hr_requests.fields.assigned_to')">{{ $hrRequest->assignee?->name ?? __('hr_requests.common.unassigned') }}</x-detail-item>
                    <x-detail-item :label="__('hr_requests.show.sla')">
                        @if ($hrRequest->due_at)
                            @if ($hrRequest->isClosed())
                                <span @class(['text-feedback-success-text' => $hrRequest->closed_at <= $hrRequest->due_at, 'text-feedback-danger-text' => $hrRequest->closed_at > $hrRequest->due_at])>
                                    {{ $hrRequest->closed_at <= $hrRequest->due_at ? __('hr_requests.show.sla_met') : __('hr_requests.show.sla_missed') }}
                                </span>
                            @else
                                @include('admin.hr-requests._sla', ['hrRequest' => $hrRequest])
                            @endif
                        @else
                            —
                        @endif
                    </x-detail-item>
                    @if ($hrRequest->first_response_at)
                        <x-detail-item :label="__('hr_requests.fields.first_response_at')"><span dir="ltr">{{ $hrRequest->first_response_at->timezone($tz)->format('Y-m-d H:i') }}</span></x-detail-item>
                    @endif
                </x-detail-list>

                @if ($canUpdate)
                    <div class="mt-4 flex flex-col gap-3 border-t border-border-subtle pt-4">
                        @if ($assignees->isNotEmpty())
                            <form method="POST" action="{{ route('admin.hr-requests.assign', $hrRequest) }}" class="flex flex-col gap-2">
                                @csrf
                                <x-form.select name="assigned_to" :label="__('hr_requests.show.assign')">
                                    <option value="">{{ __('hr_requests.common.unassigned') }}</option>
                                    @foreach ($assignees as $assignee)
                                        <option value="{{ $assignee->id }}" @selected($hrRequest->assigned_to === $assignee->id)>{{ $assignee->name }}</option>
                                    @endforeach
                                </x-form.select>
                                <x-button variant="secondary" size="sm">{{ __('hr_requests.show.assign') }}</x-button>
                            </form>
                        @elseif ($hrRequest->assigned_to !== $user->id)
                            <form method="POST" action="{{ route('admin.hr-requests.assign', $hrRequest) }}">
                                @csrf
                                <input type="hidden" name="assigned_to" value="{{ $user->id }}">
                                <x-button variant="secondary" size="sm" icon="user">{{ __('hr_requests.show.take') }}</x-button>
                            </form>
                        @endif
                        @if ($hrRequest->status === App\Enums\HrRequestStatus::Open)
                            <form method="POST" action="{{ route('admin.hr-requests.start', $hrRequest) }}">
                                @csrf
                                <x-button size="sm" icon="check-circle">{{ __('hr_requests.show.start') }}</x-button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.hr-requests.priority', $hrRequest) }}" class="flex items-end gap-2">
                            @csrf
                            <x-form.select name="priority" :label="__('hr_requests.show.priority')" class="flex-1">
                                @foreach ($priorities as $priority)
                                    <option value="{{ $priority->value }}" @selected($hrRequest->priority === $priority)>{{ $priority->label() }}</option>
                                @endforeach
                            </x-form.select>
                            <x-button variant="secondary" size="sm">{{ __('app.actions.save') }}</x-button>
                        </form>
                    </div>
                @endif
            </x-card>

            @if ($hrRequest->status === App\Enums\HrRequestStatus::PendingApproval || $hrRequest->approvalRequests->isNotEmpty())
                <x-card :title="__('hr_requests.show.approval')" data-region="approval">
                    @can('decide', $hrRequest)
                        <div class="mb-4 flex flex-col gap-3">
                            <form method="POST" action="{{ route('admin.hr-requests.decide', [$hrRequest, 'approve']) }}" class="flex flex-col gap-2">
                                @csrf
                                <x-form.input name="reason" id="approve-reason" :label="__('hr_requests.show.approval_comment')" maxlength="1000" />
                                <x-button icon="check">{{ __('hr_requests.show.approve') }}</x-button>
                            </form>
                            <form method="POST" action="{{ route('admin.hr-requests.decide', [$hrRequest, 'reject']) }}" class="flex flex-col gap-2" data-validate>
                                @csrf
                                <x-form.input name="reason" id="reject-reason" :label="__('hr_requests.show.approval_reason')" required maxlength="1000" />
                                <x-button variant="danger" icon="x">{{ __('hr_requests.show.reject') }}</x-button>
                            </form>
                        </div>
                    @endcan
                    <p class="mb-2 text-label-md text-text-secondary">{{ __('hr_requests.show.approval_history') }}</p>
                    @include('admin.payroll._approval-history', ['requests' => $hrRequest->approvalRequests])
                </x-card>
            @endif

            @if ($template && $hrRequest->status->isHandled())
                @can('issueLetter', $hrRequest)
                    <x-card :title="__('hr_requests.letters.issue')" data-region="issue-letter">
                        <form method="POST" action="{{ route('admin.hr-requests.letter', $hrRequest) }}" class="flex flex-col gap-3">
                            @csrf
                            <p class="text-body-sm text-text-secondary">{{ __('hr_requests.letters.issue_hint', ['name' => $template->name]) }}</p>
                            @if ($letterUsesSalary) <x-alert tone="info">{{ __('hr_requests.letters.salary_notice') }}</x-alert> @endif
                            <x-form.select name="language" :label="__('hr_requests.letters.language')">
                                @foreach (['ar', 'en'] as $language)
                                    <option value="{{ $language }}" @selected(($hrRequest->letter_language ?? 'ar') === $language)>{{ __('app.locales.'.$language) }}</option>
                                @endforeach
                            </x-form.select>
                            <x-form.checkbox name="resolve" value="1" :label="__('hr_requests.letters.resolve_after')" checked />
                            <x-button icon="file-text">{{ __('hr_requests.letters.issue') }}</x-button>
                        </form>
                    </x-card>
                @endcan
            @endif

            @if ($canUpdate && $hrRequest->status->isHandled())
                <x-card :title="__('hr_requests.show.actions')" data-region="status-actions">
                    <div class="flex flex-col gap-4">
                        @if ($pendingChanges->isEmpty())
                            <form method="POST" action="{{ route('admin.hr-requests.resolve', $hrRequest) }}" class="flex flex-col gap-2">
                                @csrf
                                <x-form.textarea name="resolution_note" :label="__('hr_requests.show.resolve_note')" rows="2" maxlength="5000">{{ old('resolution_note') }}</x-form.textarea>
                                <x-button icon="check-circle">{{ __('hr_requests.show.resolve') }}</x-button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.hr-requests.reject', $hrRequest) }}" class="flex flex-col gap-2" data-validate
                            data-confirm="{{ __('hr_requests.show.confirm_reject') }}" data-confirm-tone="danger">
                            @csrf
                            <x-form.input name="rejection_reason" id="request-reject-reason" :label="__('hr_requests.fields.rejection_reason')" required maxlength="1000" />
                            <x-button variant="danger" icon="x-circle">{{ __('hr_requests.show.reject') }}</x-button>
                        </form>
                    </div>
                </x-card>
            @endif

            @if (! $hrRequest->isClosed())
                @can('cancel', $hrRequest)
                    <form method="POST" action="{{ route('admin.hr-requests.cancel', $hrRequest) }}" class="flex flex-col gap-2 rounded-xl border border-border-subtle bg-surface-default p-4" data-validate
                        data-confirm="{{ __('hr_requests.show.confirm_cancel') }}" data-confirm-tone="warning">
                        @csrf
                        <x-form.input name="reason" id="cancel-reason" :label="__('hr_requests.show.cancel_reason')" required maxlength="1000" />
                        <x-button variant="ghost" icon="x">{{ __('hr_requests.show.cancel') }}</x-button>
                    </form>
                @endcan
            @endif

            <x-card :title="__('hr_requests.show.history')" data-region="history">
                <ol class="flex flex-col gap-3 border-s border-border-default ps-4">
                    @foreach ($hrRequest->events as $event)
                        <li>
                            <p class="text-body-sm text-text-primary">{{ $event->description() }}</p>
                            @if ($event->note) <p class="text-caption-md text-text-secondary">{{ $event->note }}</p> @endif
                            <p class="text-caption-md text-text-tertiary">{{ $event->actor?->name ?? __('hr_requests.common.system') }} · <span dir="ltr">{{ $event->created_at?->timezone($tz)->format('Y-m-d H:i') }}</span></p>
                        </li>
                    @endforeach
                </ol>
            </x-card>
        </div>
    </div>
@endsection
