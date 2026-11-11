@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('leave.requests.title'), 'url' => route('admin.leave-requests.index')]],
    'subtitle' => $leave->employee->employee_number.' · '.$leave->employee->name,
])

@section('title', __('leave.requests.show', ['id' => $leave->id]))

@section('page-actions')
    @can('leave_balances.view')
        <x-button variant="secondary" icon="coins" :href="route('admin.leave-balances.show', $leave->employee)">{{ __('leave.balances.open') }}</x-button>
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-card :title="__('leave.requests.details')" data-region="leave-details">
                <x-detail-list>
                    <x-detail-item :label="__('leave.fields.employee')">{{ $leave->employee->label() }} · {{ $leave->employee->department?->name }}</x-detail-item>
                    <x-detail-item :label="__('leave.fields.leave_type_id')">
                        <span class="inline-flex items-center gap-2">@include('admin.leave._swatch', ['color' => $leave->leaveType->color]) {{ $leave->leaveType->name }}</span>
                    </x-detail-item>
                    <x-detail-item :label="__('leave.fields.period')">@include('admin.leave._period')</x-detail-item>
                    <x-detail-item :label="__('leave.fields.days')"><span class="tabular-nums">{{ $leave->days }}</span></x-detail-item>
                    <x-detail-item :label="__('leave.fields.status')">@include('admin.leave._status')</x-detail-item>
                    <x-detail-item :label="__('leave.fields.requested_by')">{{ $leave->requester?->name ?? '—' }} · {{ $leave->source->label() }} · <span dir="ltr">{{ $leave->created_at?->format('Y-m-d H:i') }}</span></x-detail-item>
                    <x-detail-item :label="__('leave.fields.reason')">{{ $leave->reason ?: '—' }}</x-detail-item>
                    @if ($leave->rejection_reason)
                        <x-detail-item :label="__('leave.fields.rejection_reason')">{{ $leave->rejection_reason }}</x-detail-item>
                    @endif
                    @if ($leave->cancellation_reason)
                        <x-detail-item :label="__('leave.fields.cancellation_reason')">{{ $leave->cancellation_reason }}</x-detail-item>
                    @endif
                </x-detail-list>
            </x-card>

            <x-card :title="__('leave.requests.day_breakdown')" padding="none" data-region="leave-days">
                <ul class="divide-y divide-border-subtle">
                    @foreach ($leave->requestDays as $day)
                        <li class="flex items-center justify-between gap-3 px-5 py-2 text-body-sm">
                            <span><span dir="ltr" class="tabular-nums">{{ $day->date->format('Y-m-d') }}</span> · {{ $day->date->translatedFormat('l') }}</span>
                            <span class="flex items-center gap-2">
                                @if (! $day->is_counted)
                                    <x-badge tone="neutral">{{ __('leave.day_kinds.'.$day->day_kind) }}</x-badge>
                                @elseif ($day->day_kind !== 'working')
                                    <x-badge tone="info">{{ __('leave.day_kinds.'.$day->day_kind) }}</x-badge>
                                @endif
                                <span class="tabular-nums">{{ $day->amount }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <x-card :title="__('leave.requests.attachments')" data-region="leave-attachments">
                <ul class="flex flex-col gap-2">
                    @forelse ($leave->attachments as $file)
                        <li class="flex items-center justify-between gap-3 text-body-sm">
                            <a class="inline-flex items-center gap-2 text-text-link hover:underline" href="{{ route('admin.leave-requests.attachments.download', [$leave, $file->id]) }}">
                                <x-icon name="file-text" size="sm" /> {{ $file->file_name }}
                            </a>
                            <span class="text-caption-md text-text-tertiary">{{ number_format($file->file_size / 1024, 0) }} KB · {{ $file->uploader?->name }}</span>
                        </li>
                    @empty
                        <li class="text-body-sm text-text-tertiary">{{ __('leave.requests.no_attachments') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :title="__('leave.requests.team_away')" :subtitle="__('leave.requests.team_away_help')" padding="none" data-region="leave-conflicts">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($conflicts as $other)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-2 text-body-sm">
                            <span>{{ $other->employee->name }} · {{ $other->leaveType->name }}</span>
                            <span class="flex items-center gap-2">@include('admin.leave._period', ['leave' => $other]) @include('admin.leave._status', ['leave' => $other])</span>
                        </li>
                    @empty
                        <li class="px-5 py-3 text-body-sm text-text-tertiary">{{ __('leave.requests.no_team_away') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            @if ($balance)
                <x-card :title="__('leave.balances.title_year', ['year' => $balance->year])" data-region="leave-balance">
                    @include('admin.leave._balance-cards', ['balances' => collect([$balance]), 'single' => true])
                </x-card>
            @endif

            <x-card :title="__('leave.requests.approval')" id="decision" data-region="leave-approval">
                <ol class="flex flex-col gap-3">
                    @foreach ($leave->approvalRequests as $approval)
                        @foreach ($approval->actions as $action)
                            <li class="text-body-sm">
                                <span class="text-label-md">{{ __('leave.approval_actions.'.$action->action->value) }}</span>
                                @if ($action->step_name) · {{ $action->step_name }} @endif
                                — {{ $action->actor?->name ?? '—' }}
                                @if ($approval->workflow->key === App\Models\LeaveRequest::CANCELLATION_WORKFLOW)
                                    <x-badge tone="warning">{{ __('leave.requests.cancellation') }}</x-badge>
                                @endif
                                <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $action->created_at?->format('Y-m-d H:i') }}</span>
                                @if ($action->reason)
                                    <span class="block text-caption-md text-text-secondary">{{ $action->reason }}</span>
                                @endif
                            </li>
                        @endforeach
                    @endforeach
                    @if ($leave->approvalRequests->isEmpty())
                        <li class="text-body-sm text-text-tertiary">{{ __('leave.requests.recorded_without_approval', ['name' => $leave->decider?->name ?? '—']) }}</li>
                    @endif
                </ol>

                @if ($canDecide)
                    <div class="mt-4 flex flex-col gap-3 border-t border-border-subtle pt-4">
                        @if ($leave->status === App\Enums\LeaveRequestStatus::CancellationPending)
                            <x-alert tone="warning">{{ __('leave.requests.cancellation_decision_help') }}</x-alert>
                        @endif
                        <form method="POST" action="{{ route('admin.leave-requests.decide', [$leave, 'approve']) }}">
                            @csrf
                            <x-button icon="check" class="w-full">{{ __('leave.common.approve') }}</x-button>
                        </form>
                        <form method="POST" action="{{ route('admin.leave-requests.decide', [$leave, 'reject']) }}" class="flex flex-col gap-2" data-validate>
                            @csrf
                            <x-form.textarea name="reason" :label="__('leave.fields.rejection_reason')" required rows="2" maxlength="1000" />
                            <x-button variant="danger" icon="x">{{ __('leave.common.reject') }}</x-button>
                        </form>
                    </div>
                @endif
            </x-card>

            @if ($canCancel)
                <x-card :title="__('leave.requests.cancel_title')" :subtitle="__('leave.requests.cancel_admin_help')" data-region="leave-cancel">
                    <form method="POST" action="{{ route('admin.leave-requests.cancel', $leave) }}" class="flex flex-col gap-2" data-validate
                        data-confirm="{{ __('leave.requests.confirm_cancel') }}" data-confirm-label="{{ __('leave.requests.cancel_action') }}">
                        @csrf
                        <x-form.textarea name="reason" id="cancel-reason" :label="__('leave.fields.cancellation_reason')" required rows="2" maxlength="1000" />
                        <x-button variant="danger" icon="x-circle">{{ __('leave.requests.cancel_action') }}</x-button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
@endsection
