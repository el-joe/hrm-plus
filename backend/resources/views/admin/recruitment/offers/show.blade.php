@extends('layouts.admin', [
    'breadcrumbs' => [
        ['label' => __('recruitment.offers.title'), 'url' => route('admin.offers.index')],
    ],
    'subtitle' => $offer->application->candidate->name.' · '.$offer->application->vacancy->title,
])

@section('title', __('recruitment.offers.offer').' '.$offer->reference)

@section('page-actions')
    <x-button variant="secondary" :href="route('admin.offers.letter', $offer)" icon="file-text" target="_blank">{{ __('recruitment.offers.letter') }}</x-button>
    <x-button variant="secondary" :href="route('admin.applications.show', $offer->application)" icon="briefcase">{{ __('recruitment.interviews.open_application') }}</x-button>
    @if ($canUpdate && $offer->status->isEditable())
        <x-button variant="secondary" :href="route('admin.offers.edit', $offer)" icon="pencil">{{ __('app.actions.edit') }}</x-button>
    @endif
@endsection

@section('content')
    @php($status = $offer->status)
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-card :title="__('recruitment.offers.details')">
                <div class="mb-4">@include('admin.recruitment._badge', ['value' => $status])</div>
                <x-detail-list>
                    <x-detail-item :label="__('recruitment.fields.position_id')">{{ $offer->position?->name ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('recruitment.fields.job_title_id')">{{ $offer->jobTitle?->name ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('recruitment.fields.employment_type_id')">{{ $offer->employmentType?->name ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('recruitment.fields.basic_salary')">
                        @if ($showSalary)
                            <span dir="ltr" class="tabular-nums">{{ App\Support\Money::format($offer->basic_salary) }} {{ $offer->currency }}</span>
                        @elseif ($canSeeSalary)
                            <span class="text-text-tertiary">{{ __('recruitment.offers.no_salary') }}</span>
                        @else
                            <span class="text-text-tertiary">{{ __('recruitment.common.restricted') }}</span>
                        @endif
                    </x-detail-item>
                    <x-detail-item :label="__('recruitment.fields.start_date')"><span dir="ltr">{{ $offer->start_date->toDateString() }}</span></x-detail-item>
                    <x-detail-item :label="__('recruitment.fields.expires_on')"><span dir="ltr">{{ $offer->expires_on->toDateString() }}</span></x-detail-item>
                    <x-detail-item :label="__('recruitment.fields.probation_months')">{{ $offer->probation_months ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('recruitment.offers.created_by')">{{ $offer->creator?->name ?? '—' }}</x-detail-item>
                    @if ($offer->sent_at)
                        <x-detail-item :label="__('recruitment.fields.sent_at')"><span dir="ltr">{{ $offer->sent_at->format('Y-m-d H:i') }}</span></x-detail-item>
                    @endif
                    @if ($offer->responded_at)
                        <x-detail-item :label="__('recruitment.fields.responded_at')"><span dir="ltr">{{ $offer->responded_at->format('Y-m-d H:i') }}</span></x-detail-item>
                    @endif
                </x-detail-list>
                @if ($offer->benefits)
                    <h3 class="mt-4 text-label-md">{{ __('recruitment.fields.benefits') }}</h3>
                    <p class="whitespace-pre-line text-body-sm text-text-secondary">{{ $offer->benefits }}</p>
                @endif
                @if ($offer->response_note)
                    <p class="mt-4 text-body-sm text-text-secondary">{{ __('recruitment.fields.note') }}: {{ $offer->response_note }}</p>
                @endif
            </x-card>

            @if ($approvalRequest)
                <x-card :title="__('recruitment.offers.approval')">
                    <ul class="flex flex-col gap-2">
                        @foreach ($approvalRequest->actions as $action)
                            <li class="text-body-sm">
                                <span class="text-label-md">{{ __('app.approval_actions.'.$action->action->value) }}</span>
                                · {{ $action->actor?->name ?? '—' }} · <span dir="ltr">{{ $action->created_at->format('Y-m-d H:i') }}</span>
                                @if ($action->reason)
                                    <span class="block text-text-secondary">{{ $action->reason }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            @if ($canDecide)
                <x-card :title="__('recruitment.offers.decide')">
                    <form method="POST" action="{{ route('admin.offers.decide', [$offer, 'approve']) }}" class="flex flex-col gap-3">
                        @csrf
                        <x-form.textarea name="comment" :label="__('recruitment.fields.comment')" rows="2" maxlength="1000" />
                        <div class="flex flex-wrap gap-2">
                            <x-button icon="check">{{ __('recruitment.offers.approve') }}</x-button>
                            <x-button variant="danger" icon="x" formaction="{{ route('admin.offers.decide', [$offer, 'reject']) }}">{{ __('recruitment.offers.reject') }}</x-button>
                        </div>
                    </form>
                </x-card>
            @endif

            @if ($canUpdate)
                <x-card :title="__('recruitment.offers.next_step')">
                    <div class="flex flex-col gap-3">
                        @if ($status === App\Enums\OfferStatus::Draft)
                            <p class="text-body-sm text-text-secondary">{{ $requiresApproval ? __('recruitment.offers.submit_help') : __('recruitment.offers.approve_direct_help') }}</p>
                            <form method="POST" action="{{ route('admin.offers.transition', [$offer, 'submit']) }}">
                                @csrf
                                <x-button icon="check">{{ $requiresApproval ? __('recruitment.offers.submit') : __('recruitment.offers.finalise') }}</x-button>
                            </form>
                        @elseif ($status === App\Enums\OfferStatus::Approved)
                            <p class="text-body-sm text-text-secondary">{{ __('recruitment.offers.send_help') }}</p>
                            <form method="POST" action="{{ route('admin.offers.transition', [$offer, 'send']) }}" data-confirm="{{ __('recruitment.offers.send_confirm', ['name' => $offer->application->candidate->name]) }}" data-confirm-tone="info">
                                @csrf
                                <x-button icon="mail">{{ __('recruitment.offers.send') }}</x-button>
                            </form>
                        @elseif ($status === App\Enums\OfferStatus::Sent)
                            <p class="text-body-sm text-text-secondary">{{ __('recruitment.offers.respond_help') }}</p>
                            <form method="POST" action="{{ route('admin.offers.transition', [$offer, 'accept']) }}" class="flex flex-col gap-2">
                                @csrf
                                <x-form.input name="note" :label="__('recruitment.fields.note')" maxlength="1000" />
                                <div class="flex flex-wrap gap-2">
                                    <x-button icon="check-circle">{{ __('recruitment.offers.accepted') }}</x-button>
                                    <x-button variant="secondary" icon="x-circle" formaction="{{ route('admin.offers.transition', [$offer, 'decline']) }}">{{ __('recruitment.offers.declined') }}</x-button>
                                </div>
                            </form>
                        @elseif ($status === App\Enums\OfferStatus::Accepted && $offer->application->isActive())
                            @can('convert', $offer->application)
                                <x-button :href="route('admin.applications.convert', $offer->application)" icon="user-plus">{{ __('recruitment.applications.hire') }}</x-button>
                            @endcan
                        @elseif ($status === App\Enums\OfferStatus::PendingApproval)
                            <p class="text-body-sm text-text-secondary">{{ __('recruitment.offers.pending_help') }}</p>
                        @endif

                        @if ($status->isOpen())
                            <form method="POST" action="{{ route('admin.offers.transition', [$offer, 'withdraw']) }}" data-confirm="{{ __('recruitment.offers.withdraw_confirm') }}" data-confirm-tone="danger">
                                @csrf
                                <x-button variant="ghost" icon="x">{{ __('recruitment.offers.withdraw') }}</x-button>
                            </form>
                        @endif
                    </div>
                </x-card>
            @endif
        </div>
    </div>
@endsection
