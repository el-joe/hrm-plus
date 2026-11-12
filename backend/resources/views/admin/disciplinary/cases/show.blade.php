@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('disciplinary.cases.title'), 'url' => route('admin.disciplinary-cases.index')]],
    'subtitle' => $case->employee->label(),
])

@section('title', $case->reference.' · '.$case->violationType->name)

@section('page-actions')
    @can('view', $case->employee)
        <x-button variant="secondary" :href="route('admin.employees.tab', [$case->employee, 'disciplinary'])" icon="user">{{ __('disciplinary.tab.title') }}</x-button>
    @endcan
    @if ($canUpdate && $case->status->acceptsProposal() && ! $case->actions->contains(fn ($action) => $action->isPending()))
        <x-button icon="plus" :href="route('admin.disciplinary-cases.actions.create', $case)">{{ __('disciplinary.actions.propose') }}</x-button>
    @endif
@endsection

@section('content')
    <x-alert tone="warning">{{ __('disciplinary.restricted') }}</x-alert>

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
            <x-card :title="__('disciplinary.cases.incident')" data-region="incident">
                <x-slot:actions>@include('admin.disciplinary.cases._status', ['status' => $case->status])</x-slot:actions>
                <x-detail-list>
                    <x-detail-item :label="__('disciplinary.fields.employee')">
                        @can('view', $case->employee)
                            <a class="text-text-link hover:underline" href="{{ route('admin.employees.show', $case->employee) }}">{{ $case->employee->label() }}</a>
                        @else
                            {{ $case->employee->label() }}
                        @endcan
                        <span class="block text-caption-md text-text-tertiary">{{ $case->employee->jobTitle?->name }} · {{ $case->department?->name }}</span>
                    </x-detail-item>
                    <x-detail-item :label="__('disciplinary.fields.violation_type')">
                        {{ $case->violationType->name }} <x-badge :tone="$case->violationType->severity->tone()">{{ $case->violationType->severity->label() }}</x-badge>
                    </x-detail-item>
                    <x-detail-item :label="__('disciplinary.fields.incident_date')"><span dir="ltr">{{ $case->incident_date->toDateString() }}</span></x-detail-item>
                    <x-detail-item :label="__('disciplinary.fields.location')">{{ $case->location ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('disciplinary.fields.reported_by')">{{ __('disciplinary.cases.reported_by_on', ['name' => $case->reporter?->name ?? '—', 'date' => $case->created_at->toDateString()]) }}</x-detail-item>
                    <x-detail-item :label="__('disciplinary.fields.investigator')">{{ $case->investigator?->name ?? '—' }}</x-detail-item>
                </x-detail-list>
                <h3 class="mt-4 text-label-md text-text-secondary">{{ __('disciplinary.fields.description') }}</h3>
                <p class="mt-1 whitespace-pre-line text-body-md text-text-primary">{{ $case->description }}</p>
                @if ($case->closure_note)
                    <h3 class="mt-4 text-label-md text-text-secondary">{{ __('disciplinary.fields.closure_note') }}</h3>
                    <p class="mt-1 whitespace-pre-line text-body-sm text-text-primary">{{ $case->closure_note }}</p>
                    <p class="text-caption-md text-text-tertiary">{{ __('disciplinary.cases.closed_by_on', ['name' => $case->closer?->name ?? '—', 'date' => $case->closed_at?->toDateString()]) }}</p>
                @endif
            </x-card>

            <x-card :title="__('disciplinary.actions.title')" data-region="actions">
                <ul class="flex flex-col divide-y divide-border-subtle">
                    @forelse ($case->actions as $action)
                        <li class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0" data-action-id="{{ $action->id }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                @include('admin.disciplinary.cases._action', ['action' => $action])
                                <x-badge :tone="$action->status->tone()">{{ $action->status->label() }}</x-badge>
                            </div>
                            <p class="whitespace-pre-line text-body-sm text-text-primary">{{ $action->details }}</p>
                            <dl class="grid gap-x-4 gap-y-1 text-body-sm sm:grid-cols-2">
                                <div class="flex gap-1"><dt class="text-text-tertiary">{{ __('disciplinary.fields.effective_date') }}:</dt><dd dir="ltr">{{ $action->effective_date->toDateString() }}</dd></div>
                                @if ($action->expires_on)
                                    <div class="flex gap-1"><dt class="text-text-tertiary">{{ __('disciplinary.fields.expires_on') }}:</dt><dd dir="ltr">{{ $action->expires_on->toDateString() }}</dd></div>
                                @endif
                                @if ($action->suggested_level)
                                    <div class="flex gap-1"><dt class="text-text-tertiary">{{ __('disciplinary.actions.suggestion', ['type' => App\Enums\DisciplinaryActionType::forLevel($action->suggested_level)->label()]) }}</dt></div>
                                @endif
                                @if ($action->suspension_start)
                                    <div class="flex gap-1"><dt class="text-text-tertiary">{{ __('disciplinary.actions.suspension') }}:</dt>
                                        <dd><span dir="ltr">{{ $action->suspension_start->toDateString() }} → {{ $action->suspension_end?->toDateString() }}</span> · {{ $action->suspension_paid ? __('disciplinary.actions.paid') : __('disciplinary.actions.unpaid') }}</dd></div>
                                @endif
                                @if ($action->deduction_amount !== null)
                                    <div class="flex gap-1"><dt class="text-text-tertiary">{{ __('disciplinary.fields.deduction_amount') }}:</dt><dd>@include('admin.payroll._money', ['amount' => $action->deduction_amount, 'currency' => setting('localisation.currency_code')])</dd></div>
                                @endif
                                @if ($action->payEntry)
                                    <div class="flex gap-1"><dt class="text-text-tertiary">{{ __('disciplinary.actions.pay_entry') }}:</dt>
                                        <dd>
                                            @can('view', $action->payEntry)
                                                <a class="text-text-link hover:underline" dir="ltr" href="{{ route('admin.deductions.show', $action->payEntry) }}">{{ $action->payEntry->reference }}</a>
                                            @else
                                                <span dir="ltr">{{ $action->payEntry->reference }}</span>
                                            @endcan
                                            · @include('admin.payroll._request-status', ['status' => $action->payEntry->status])
                                        </dd></div>
                                @endif
                            </dl>
                            <p class="text-caption-md text-text-tertiary">
                                {{ __('disciplinary.actions.proposed_by', ['name' => $action->proposer?->name ?? '—']) }}
                                @if ($action->decided_at) · {{ __('disciplinary.actions.decided_by', ['name' => $action->decider?->name ?? '—', 'date' => $action->decided_at->toDateString()]) }} @endif
                                @if ($action->issued_at) · {{ __('disciplinary.actions.issued_on', ['date' => $action->issued_at->toDateString()]) }} @endif
                            </p>
                            @if ($action->rejection_reason)
                                <p class="text-body-sm text-feedback-danger-text">{{ $action->rejection_reason }}</p>
                            @endif
                            @if ($action->type === App\Enums\DisciplinaryActionType::Termination && $action->isIssued())
                                <x-alert tone="info">
                                    {{ __('disciplinary.actions.termination_note') }}
                                    @can('create', App\Models\OffboardingCase::class)
                                        <a class="ms-1 text-text-link hover:underline" href="{{ route('admin.offboarding.create', ['employee' => $action->employee_id]) }}">{{ __('disciplinary.actions.open_offboarding') }}</a>
                                    @endcan
                                </x-alert>
                            @endif
                            @if ($action->isIssued())
                                <div class="rounded-lg border border-border-subtle bg-surface-muted p-3">
                                    <p class="text-label-md text-text-secondary">{{ __('disciplinary.actions.employee_response') }}</p>
                                    @if ($action->employee_response)
                                        <p class="mt-1 whitespace-pre-line text-body-sm text-text-primary">{{ $action->employee_response }}</p>
                                        <p class="text-caption-md text-text-tertiary" dir="ltr">{{ $action->responded_at?->format('Y-m-d H:i') }}</p>
                                    @else
                                        <p class="mt-1 text-body-sm text-text-tertiary">{{ __('disciplinary.actions.no_response') }}
                                            @if ($action->response_due_on) · {{ __('disciplinary.fields.response_due_on') }} <span dir="ltr">{{ $action->response_due_on->toDateString() }}</span>@endif</p>
                                    @endif
                                    @if ($action->acknowledged_at)
                                        <p class="text-caption-md text-text-tertiary">{{ __('disciplinary.actions.acknowledged_on', ['date' => $action->acknowledged_at->toDateString()]) }}</p>
                                    @endif
                                </div>
                            @endif
                            <details class="text-body-sm">
                                <summary class="cursor-pointer text-text-link">{{ __('disciplinary.actions.approval_history') }}</summary>
                                <div class="mt-2">@include('admin.payroll._approval-history', ['requests' => $action->approvalRequests])</div>
                            </details>
                            @can('decide', $action)
                                <div class="flex flex-col gap-3 rounded-lg border border-border-default p-3 sm:flex-row sm:items-end" data-region="decision">
                                    <form method="POST" action="{{ route('admin.disciplinary-cases.actions.decide', [$case, $action, 'approve']) }}" class="flex flex-1 flex-col gap-2">
                                        @csrf
                                        <x-form.input name="reason" :id="'approve-reason-'.$action->id" :label="__('disciplinary.actions.comment')" maxlength="1000" />
                                        <x-button icon="check">{{ __('disciplinary.actions.approve') }}</x-button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.disciplinary-cases.actions.decide', [$case, $action, 'reject']) }}" class="flex flex-1 flex-col gap-2" data-validate>
                                        @csrf
                                        <x-form.input name="reason" :id="'reject-reason-'.$action->id" :label="__('disciplinary.actions.reject_reason')" required maxlength="1000" />
                                        <x-button variant="danger" icon="x-circle">{{ __('disciplinary.actions.reject') }}</x-button>
                                    </form>
                                </div>
                            @endcan
                            @can('cancel', $action)
                                <form method="POST" action="{{ route('admin.disciplinary-cases.actions.cancel', [$case, $action]) }}" class="flex flex-col gap-2 sm:flex-row sm:items-end" data-validate
                                    data-confirm="{{ __('disciplinary.actions.withdraw') }}?" data-confirm-tone="warning">
                                    @csrf
                                    <x-form.input name="reason" :id="'cancel-reason-'.$action->id" :label="__('disciplinary.fields.reason')" required maxlength="1000" class="sm:w-72" />
                                    <x-button variant="ghost" icon="x">{{ __('disciplinary.actions.withdraw') }}</x-button>
                                </form>
                            @endcan
                        </li>
                    @empty
                        <li class="text-body-sm text-text-tertiary">{{ __('disciplinary.actions.no_actions') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :title="__('disciplinary.cases.investigation')" data-region="investigation">
                <ol class="flex flex-col gap-3">
                    @forelse ($case->notes as $note)
                        <li class="flex flex-col gap-0.5 border-s-2 border-border-subtle ps-3">
                            <span class="text-label-md text-text-primary">{{ $note->type->label() }}</span>
                            <p class="whitespace-pre-line text-body-sm text-text-primary">{{ $note->body }}</p>
                            <span class="text-caption-md text-text-tertiary">{{ $note->author?->name }} · <span dir="ltr">{{ $note->created_at->format('Y-m-d H:i') }}</span></span>
                        </li>
                    @empty
                        <li class="text-body-sm text-text-tertiary">{{ __('disciplinary.cases.no_notes') }}</li>
                    @endforelse
                </ol>
                @if ($canUpdate && $case->isOpen())
                    <form method="POST" action="{{ route('admin.disciplinary-cases.notes.store', $case) }}" class="mt-4 grid gap-3 md:grid-cols-[12rem_1fr]" data-validate>
                        @csrf
                        <x-form.select name="type" :label="__('disciplinary.fields.type')" required>
                            @foreach ($noteTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.textarea name="body" :label="__('disciplinary.fields.body')" rows="3" maxlength="10000" required />
                        <div class="md:col-span-2"><x-button variant="secondary" icon="plus">{{ __('disciplinary.cases.add_note') }}</x-button></div>
                    </form>

                    <form method="POST" action="{{ route('admin.disciplinary-cases.findings', $case) }}" class="mt-4 flex flex-col gap-2 border-t border-border-subtle pt-4" data-validate>
                        @csrf
                        @method('PUT')
                        <x-form.textarea name="findings" :label="__('disciplinary.fields.findings')" rows="3" maxlength="10000" required>{{ old('findings', $case->findings) }}</x-form.textarea>
                        <div><x-button variant="secondary" icon="check">{{ __('disciplinary.cases.save_findings') }}</x-button></div>
                    </form>
                @elseif ($case->findings)
                    <h3 class="mt-4 text-label-md text-text-secondary">{{ __('disciplinary.fields.findings') }}</h3>
                    <p class="mt-1 whitespace-pre-line text-body-sm text-text-primary">{{ $case->findings }}</p>
                @endif
            </x-card>

            <x-card :title="__('disciplinary.fields.evidence')" padding="none" data-region="evidence">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($case->evidence as $file)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                            <div class="min-w-0">
                                <a class="text-label-md text-text-link hover:underline" href="{{ route('admin.disciplinary-cases.evidence.download', [$case, $file]) }}">{{ $file->file_name }}</a>
                                <p class="text-caption-md text-text-tertiary">{{ $file->description ? $file->description.' · ' : '' }}{{ $file->uploader?->name }} · <span dir="ltr">{{ $file->created_at->toDateString() }}</span></p>
                            </div>
                            <x-icon-button icon="download" size="sm" :href="route('admin.disciplinary-cases.evidence.download', [$case, $file])" :label="__('disciplinary.cases.download')" />
                        </li>
                    @empty
                        <li class="px-5 py-4 text-body-sm text-text-tertiary">{{ __('disciplinary.cases.no_evidence') }}</li>
                    @endforelse
                </ul>
                @if ($canUpdate && $case->isOpen())
                    <x-slot:footer>
                        <form method="POST" action="{{ route('admin.disciplinary-cases.evidence.store', $case) }}" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-2 md:items-end" data-validate>
                            @csrf
                            <x-form.file-input name="evidence[]" :label="__('disciplinary.fields.evidence')" error="evidence" :error-keys="['evidence.*']" multiple required />
                            <x-form.input name="evidence_description" :label="__('disciplinary.fields.evidence_description')" maxlength="500" />
                            <div class="md:col-span-2"><x-button variant="secondary" icon="upload">{{ __('disciplinary.cases.upload_evidence') }}</x-button></div>
                        </form>
                    </x-slot:footer>
                @endif
            </x-card>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            @if ($canUpdate && $case->status === App\Enums\DisciplinaryCaseStatus::Reported)
                <x-card :title="__('disciplinary.cases.start_investigation')">
                    <form method="POST" action="{{ route('admin.disciplinary-cases.investigate', $case) }}" class="flex flex-col gap-3">
                        @csrf
                        <x-form.select name="investigator_id" :label="__('disciplinary.fields.investigator')">
                            <option value="">{{ auth()->user()->name }}</option>
                            @foreach ($investigators->reject(fn ($user) => $user->id === auth()->id()) as $investigator)
                                <option value="{{ $investigator->id }}">{{ $investigator->name }}</option>
                            @endforeach
                        </x-form.select>
                        <x-button icon="search">{{ __('disciplinary.cases.start_investigation') }}</x-button>
                    </form>
                </x-card>
            @endif

            @if ($suggestion)
                <x-card :title="__('disciplinary.actions.suggestion', ['type' => $suggestion['type']->label()])" data-region="suggestion">
                    <p class="text-body-sm text-text-secondary">
                        {{ $suggestion['escalated'] ? __('disciplinary.actions.suggestion_escalated', ['count' => $suggestion['active']->count()]) : __('disciplinary.actions.suggestion_first', ['type' => $suggestion['type']->label()]) }}
                    </p>
                    @if ($canUpdate && ! $case->actions->contains(fn ($action) => $action->isPending()))
                        <div class="mt-3"><x-button size="sm" icon="plus" :href="route('admin.disciplinary-cases.actions.create', $case)">{{ __('disciplinary.actions.propose') }}</x-button></div>
                    @endif
                </x-card>
            @endif

            <x-card :title="__('disciplinary.actions.active_warnings')" data-region="active-warnings">
                <ul class="flex flex-col gap-2">
                    @forelse ($activeWarnings as $warning)
                        <li class="flex flex-wrap items-center justify-between gap-2 text-body-sm">
                            <span><x-badge :tone="$warning->type->tone()">{{ $warning->type->label() }}</x-badge> <span dir="ltr" class="text-text-tertiary">{{ $warning->disciplinaryCase->reference }}</span></span>
                            <span class="text-caption-md text-text-tertiary">{{ __('disciplinary.actions.warning_until', ['date' => $warning->expires_on->toDateString()]) }}</span>
                        </li>
                    @empty
                        <li class="text-body-sm text-text-tertiary">{{ __('disciplinary.actions.no_active_warnings') }}</li>
                    @endforelse
                </ul>
            </x-card>

            @if ($canUpdate && $case->status->acceptsProposal())
                <x-card :title="__('disciplinary.cases.dismiss')" :subtitle="__('disciplinary.cases.dismiss_help')">
                    <form method="POST" action="{{ route('admin.disciplinary-cases.dismiss', $case) }}" class="flex flex-col gap-2" data-validate
                        data-confirm="{{ __('disciplinary.cases.dismiss_confirm') }}" data-confirm-tone="warning">
                        @csrf
                        <x-form.textarea name="closure_note" id="dismiss-note" :label="__('disciplinary.fields.closure_note')" rows="2" maxlength="2000" required />
                        <x-button variant="secondary" icon="x-circle">{{ __('disciplinary.cases.dismiss') }}</x-button>
                    </form>
                </x-card>
            @endif

            @if ($canUpdate && $case->status === App\Enums\DisciplinaryCaseStatus::ActionIssued)
                <x-card :title="__('disciplinary.cases.close')" :subtitle="__('disciplinary.cases.close_help')">
                    <form method="POST" action="{{ route('admin.disciplinary-cases.close', $case) }}" class="flex flex-col gap-2">
                        @csrf
                        <x-form.textarea name="closure_note" id="close-note" :label="__('disciplinary.fields.closure_note')" rows="2" maxlength="2000" />
                        <x-button variant="secondary" icon="check-circle">{{ __('disciplinary.cases.close') }}</x-button>
                    </form>
                </x-card>
            @endif

            <x-card :title="__('disciplinary.cases.history')" padding="none">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($history as $other)
                        <li class="flex flex-col gap-1 px-5 py-3">
                            <a class="text-label-md text-text-link hover:underline" href="{{ route('admin.disciplinary-cases.show', $other) }}"><span dir="ltr">{{ $other->reference }}</span> · {{ $other->violationType->name }}</a>
                            <span class="flex flex-wrap items-center gap-1 text-caption-md text-text-tertiary">
                                <span dir="ltr">{{ $other->incident_date->toDateString() }}</span>
                                @include('admin.disciplinary.cases._status', ['status' => $other->status])
                            </span>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-body-sm text-text-tertiary">{{ __('disciplinary.cases.no_history') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
@endsection
