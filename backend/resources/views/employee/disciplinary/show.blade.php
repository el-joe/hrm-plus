@extends('layouts.employee', ['subtitle' => $notice->disciplinaryCase->violationType->name])

@section('title', $notice->type->label().' · '.$notice->disciplinaryCase->reference)

@section('content')
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('disciplinary.portal.notice')" class="lg:col-span-2" data-region="notice">
            <x-slot:actions><x-badge :tone="$notice->type->tone()">{{ $notice->type->label() }}</x-badge></x-slot:actions>
            <x-detail-list>
                <x-detail-item :label="__('disciplinary.portal.violation')">{{ $notice->disciplinaryCase->violationType->name }}</x-detail-item>
                <x-detail-item :label="__('disciplinary.fields.incident_date')"><span dir="ltr">{{ $notice->disciplinaryCase->incident_date->toDateString() }}</span></x-detail-item>
                <x-detail-item :label="__('disciplinary.fields.effective_date')"><span dir="ltr">{{ $notice->effective_date->toDateString() }}</span></x-detail-item>
                @if ($notice->expires_on)
                    <x-detail-item :label="__('disciplinary.fields.expires_on')"><span dir="ltr">{{ $notice->expires_on->toDateString() }}</span></x-detail-item>
                @endif
                @if ($notice->suspension_start)
                    <x-detail-item :label="__('disciplinary.actions.suspension')">
                        <span dir="ltr">{{ $notice->suspension_start->toDateString() }} → {{ $notice->suspension_end?->toDateString() }}</span> · {{ $notice->suspension_paid ? __('disciplinary.actions.paid') : __('disciplinary.actions.unpaid') }}
                    </x-detail-item>
                @endif
                @if ($notice->deduction_amount !== null)
                    <x-detail-item :label="__('disciplinary.fields.deduction_amount')">@include('admin.payroll._money', ['amount' => $notice->deduction_amount, 'currency' => setting('localisation.currency_code')])</x-detail-item>
                @endif
            </x-detail-list>
            <p class="mt-4 whitespace-pre-line text-body-md text-text-primary">{{ $notice->details }}</p>
        </x-card>

        <div class="flex flex-col gap-4 lg:gap-6">
            @if ($notice->hasResponded())
                <x-card :title="__('disciplinary.portal.your_response')" data-region="response">
                    <p class="whitespace-pre-line text-body-sm text-text-primary">{{ $notice->employee_response }}</p>
                    <p class="mt-2 text-caption-md text-text-tertiary" dir="ltr">{{ $notice->responded_at->format('Y-m-d H:i') }}</p>
                </x-card>
            @else
                <x-card :title="__('disciplinary.portal.respond')" :subtitle="__('disciplinary.portal.respond_help')" data-region="response-form">
                    @if ($notice->response_due_on)
                        <p class="mb-3 text-body-sm {{ $notice->response_due_on->isPast() && ! $notice->response_due_on->isToday() ? 'text-feedback-danger-text' : 'text-text-secondary' }}">
                            {{ $notice->response_due_on->isPast() && ! $notice->response_due_on->isToday()
                                ? __('disciplinary.portal.response_overdue', ['date' => $notice->response_due_on->toDateString()])
                                : __('disciplinary.portal.response_due', ['date' => $notice->response_due_on->toDateString()]) }}
                        </p>
                    @endif
                    <form method="POST" action="{{ route('portal.disciplinary.respond', $notice->id) }}" class="flex flex-col gap-3" data-validate>
                        @csrf
                        <x-form.textarea name="response" :label="__('disciplinary.fields.response')" rows="6" minlength="3" maxlength="5000" required>{{ old('response') }}</x-form.textarea>
                        <x-button icon="check">{{ __('disciplinary.portal.respond') }}</x-button>
                    </form>
                </x-card>
            @endif

            <x-card :title="__('disciplinary.portal.acknowledge')">
                @if ($notice->acknowledged_at)
                    <p class="text-body-sm text-text-secondary">{{ __('disciplinary.actions.acknowledged_on', ['date' => $notice->acknowledged_at->toDateString()]) }}</p>
                @else
                    <form method="POST" action="{{ route('portal.disciplinary.acknowledge', $notice->id) }}">
                        @csrf
                        <x-button variant="secondary" icon="check-circle">{{ __('disciplinary.portal.acknowledge') }}</x-button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
@endsection
