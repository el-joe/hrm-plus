@extends('layouts.employee')

@section('title', __('payroll.loans.portal.title'))

@section('content')
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('payroll.loans.portal.history')" padding="none" class="lg:col-span-2" data-region="my-loans">
            <ul class="divide-y divide-border-subtle">
                @forelse ($loans as $loan)
                    <li class="flex flex-col gap-2 px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <a href="{{ route('portal.loans.show', $loan->id) }}" class="text-label-md text-text-link hover:underline">{{ $loan->type->label() }} · <span dir="ltr">{{ $loan->reference }}</span></a>
                            @include('admin.payroll._loan-status')
                        </div>
                        <p class="text-body-sm text-text-secondary">
                            @include('admin.payroll._money', ['amount' => $loan->approved_amount ?? $loan->requested_amount, 'currency' => $currency])
                            · {{ $loan->instalment_count ?? $loan->requested_instalments }} × <span dir="ltr">{{ ($loan->start_date ?? $loan->requested_start)->format('Y-m') }}</span>
                            @if ($loan->isActive())
                                · {{ __('payroll.loans.portal.remaining', ['amount' => App\Support\Money::format($loan->outstanding_amount)]) }}
                            @endif
                            @if ($loan->isPending() && $loan->latestApprovalRequest?->currentStep)
                                · {{ __('payroll.common.at_step', ['step' => $loan->latestApprovalRequest->currentStep->name]) }}
                            @endif
                        </p>
                        @if ($loan->rejection_reason)
                            <x-alert tone="danger">{{ __('payroll.loans.portal.rejected_because', ['reason' => $loan->rejection_reason]) }}</x-alert>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-6"><x-empty-state icon="wallet" :title="__('payroll.loans.portal.no_loans')" /></li>
                @endforelse
            </ul>
            <x-slot:footer>
                <x-pagination :paginator="$loans" />
            </x-slot:footer>
        </x-card>

        <x-card :title="__('payroll.loans.portal.new_request')" :subtitle="__('payroll.loans.portal.new_request_help')" data-region="loan-request-form">
            @if (! $requestsEnabled)
                <p class="text-body-sm text-text-tertiary">{{ __('payroll.loans.portal.disabled') }}</p>
            @else
                <form method="POST" action="{{ route('portal.loans.store') }}" class="grid gap-3" data-validate>
                    @csrf
                    @include('admin.payroll.loans._request-fields')
                    <x-button icon="check">{{ __('payroll.loans.portal.send') }}</x-button>
                </form>
            @endif
        </x-card>
    </div>
@endsection
