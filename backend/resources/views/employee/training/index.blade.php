@extends('layouts.employee')

@section('title', __('training.portal.title'))

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
            <x-card :title="__('training.portal.my_enrolments')" padding="none" data-region="my-enrolments">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($enrolments as $enrolment)
                        <li class="flex flex-col gap-2 px-5 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <a href="{{ route('portal.training.enrolments.show', $enrolment->id) }}" class="text-label-md text-text-link hover:underline">{{ $enrolment->session->course->name }}</a>
                                <x-badge :tone="$enrolment->status->tone()" dot>{{ $enrolment->status->label() }}</x-badge>
                            </div>
                            <p class="text-body-sm text-text-secondary">
                                @include('admin.training._session-when', ['session' => $enrolment->session])
                                · {{ $enrolment->session->delivery_mode->label() }}@if ($enrolment->session->location) · {{ $enrolment->session->location }}@endif
                                @if ($enrolment->isPending() && $enrolment->latestApprovalRequest?->currentStep)
                                    · {{ __('training.common.at_step', ['step' => $enrolment->latestApprovalRequest->currentStep->name]) }}
                                @endif
                            </p>
                            @if ($enrolment->rejection_reason && $enrolment->status === App\Enums\TrainingEnrolmentStatus::Rejected)
                                <x-alert tone="danger">{{ __('training.portal.rejected_because', ['reason' => $enrolment->rejection_reason]) }}</x-alert>
                            @endif
                            @if ($enrolment->isPending())
                                <form method="POST" action="{{ route('portal.training.enrolments.cancel', $enrolment->id) }}" data-confirm="{{ __('training.portal.withdraw_confirm') }}" data-confirm-tone="warning">
                                    @csrf
                                    <x-button size="sm" variant="ghost" icon="x">{{ __('training.portal.withdraw') }}</x-button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-6"><x-empty-state icon="book-open" :title="__('training.portal.no_enrolments')" /></li>
                    @endforelse
                </ul>
                <x-slot:footer>
                    <x-pagination :paginator="$enrolments" />
                </x-slot:footer>
            </x-card>

            <x-card :title="__('training.certificates.title')" padding="none" id="certificates" data-region="my-certificates">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($certificates as $certificate)
                        <li class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-label-md text-text-primary">{{ $certificate->name }}</p>
                                <p class="text-caption-md text-text-tertiary">{{ $certificate->type->label() }}@if ($certificate->issuer) · {{ $certificate->issuer }}@endif
                                    @if ($certificate->expires_on) · {{ __('training.portal.expires', ['date' => $certificate->expires_on->toDateString()]) }}@endif</p>
                            </div>
                            <div class="flex items-center gap-2">
                                @include('admin.training._validity', ['reminderDays' => $reminderDays])
                                @if ($certificate->hasFile())
                                    <x-icon-button icon="download" size="sm" :href="route('portal.training.certificates.download', $certificate->id)" :label="__('training.certificates.download')" />
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-6"><x-empty-state icon="award" :title="__('training.certificates.empty')" /></li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('training.portal.request')" :subtitle="__('training.portal.request_help')" data-region="training-request-form">
                @if (! $requestsEnabled)
                    <p class="text-body-sm text-text-tertiary">{{ __('training.validation.requests_disabled') }}</p>
                @elseif ($openSessions->isEmpty())
                    <p class="text-body-sm text-text-tertiary">{{ __('training.portal.no_open_sessions') }}</p>
                @else
                    <form method="POST" action="{{ route('portal.training.requests.store') }}" class="flex flex-col gap-3" data-validate>
                        @csrf
                        <x-form.select name="training_session_id" :label="__('training.fields.session')" required>
                            <option value="">—</option>
                            @foreach ($openSessions as $session)
                                <option value="{{ $session->id }}" @selected((string) old('training_session_id') === (string) $session->id)>{{ $session->course->name }} · {{ $session->start_date->toDateString() }} ({{ $session->delivery_mode->label() }})</option>
                            @endforeach
                        </x-form.select>
                        <x-form.textarea name="reason" :label="__('training.fields.reason')" maxlength="1000" rows="3">{{ old('reason') }}</x-form.textarea>
                        <x-button icon="check">{{ __('training.portal.send_request') }}</x-button>
                    </form>
                @endif
            </x-card>

            <x-card :title="__('training.portal.my_skills')">
                <ul class="flex flex-col gap-2">
                    @forelse ($skills as $row)
                        <li class="flex items-center justify-between gap-2">
                            <span class="text-body-sm text-text-primary">{{ $row->skill->name }}</span>
                            @include('admin.training._level', ['level' => $row->level])
                        </li>
                    @empty
                        <li class="text-body-sm text-text-tertiary">{{ __('training.skills_matrix.none') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :title="__('training.portal.upload_certificate')" :subtitle="__('training.portal.upload_help')" data-region="certificate-upload">
                <form method="POST" action="{{ route('portal.training.certificates.store') }}" enctype="multipart/form-data" class="grid gap-3" data-validate>
                    @csrf
                    @include('admin.training.certificates._fields', ['fileRequired' => true])
                    <x-button variant="secondary" icon="upload">{{ __('training.portal.upload') }}</x-button>
                </form>
            </x-card>
        </div>
    </div>
@endsection
