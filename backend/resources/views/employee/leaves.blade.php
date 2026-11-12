@extends('layouts.employee')

@section('title', __('leave.portal.title'))

@section('content')
    <section class="flex flex-col gap-3" aria-labelledby="my-balances">
        <h2 id="my-balances" class="text-h4 text-text-primary">{{ __('leave.portal.balances', ['year' => $yearLabel]) }}</h2>
        @include('admin.leave._balance-cards', ['compact' => true])
    </section>

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('leave.portal.history')" padding="none" class="lg:col-span-2" data-region="my-leave-requests">
            <ul class="divide-y divide-border-subtle">
                @forelse ($requests as $leave)
                    @php $approval = $leave->latestApprovalRequest; @endphp
                    <li class="flex flex-col gap-2 px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-2 text-label-md text-text-primary">@include('admin.leave._swatch', ['color' => $leave->leaveType->color]) {{ $leave->leaveType->name }}</span>
                            @include('admin.leave._status')
                        </div>
                        <p class="text-body-sm text-text-secondary">
                            @include('admin.leave._period')
                            · {{ __('leave.portal.days_charged', ['days' => $leave->days]) }}
                            @if ($approval?->isPending() && $approval->currentStep)
                                · {{ __('leave.requests.at_step', ['step' => $approval->currentStep->name]) }}
                            @endif
                        </p>
                        @if ($leave->reason)
                            <p class="text-caption-md text-text-tertiary">{{ $leave->reason }}</p>
                        @endif
                        @if ($leave->rejection_reason)
                            <x-alert tone="danger">{{ __('leave.portal.rejected_because', ['reason' => $leave->rejection_reason]) }}</x-alert>
                        @endif
                        @if ($leave->attachments->isNotEmpty())
                            <ul class="flex flex-wrap gap-3 text-caption-md">
                                @foreach ($leave->attachments as $file)
                                    <li><a class="inline-flex items-center gap-1 text-text-link hover:underline" href="{{ route('portal.leaves.attachments.download', [$leave->id, $file->id]) }}"><x-icon name="file-text" size="sm" />{{ $file->file_name }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                        @if (in_array($leave->status, [App\Enums\LeaveRequestStatus::Pending, App\Enums\LeaveRequestStatus::Approved], true))
                            @php
                                $needsApproval = $leave->status === App\Enums\LeaveRequestStatus::Approved
                                    && (! setting('leave.cancel_before_start_directly') || $leave->start_date->format('Y-m-d') <= $today->format('Y-m-d'));
                            @endphp
                            <form method="POST" action="{{ route('portal.leaves.cancel', $leave->id) }}" class="flex flex-col gap-2 sm:flex-row sm:items-end"
                                data-confirm="{{ $needsApproval ? __('leave.portal.confirm_cancellation_request') : __('leave.portal.confirm_cancel') }}" @if ($needsApproval) data-validate @endif>
                                @csrf
                                @if ($needsApproval)
                                    <x-form.input name="reason" id="cancel-reason-{{ $leave->id }}" :label="__('leave.fields.cancellation_reason')" required maxlength="1000" class="sm:w-72" />
                                @endif
                                <x-button variant="ghost" size="sm" icon="x-circle">{{ $needsApproval ? __('leave.portal.request_cancellation') : __('leave.portal.cancel') }}</x-button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-6"><x-empty-state icon="calendar" :title="__('leave.portal.no_requests')" /></li>
                @endforelse
            </ul>
            <x-slot:footer>
                <x-pagination :paginator="$requests" />
            </x-slot:footer>
        </x-card>

        <x-card :title="__('leave.portal.new_request')" :subtitle="__('leave.portal.new_request_help')" id="new-leave" class="scroll-mt-24" data-region="leave-request-form">
            @if ($types->isEmpty())
                <p class="text-body-sm text-text-tertiary">{{ __('leave.portal.no_types') }}</p>
            @else
                <form method="POST" action="{{ route('portal.leaves.store') }}" enctype="multipart/form-data" class="grid gap-3" data-validate data-leave-form>
                    @csrf
                    @include('admin.leave._request-fields')
                    <x-button icon="check">{{ __('leave.portal.send') }}</x-button>
                </form>
            @endif
        </x-card>
    </div>
@endsection
