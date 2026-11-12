@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('onboarding.offboarding.title'), 'url' => route('admin.offboarding.index')]],
    'subtitle' => $case->employee->employee_number.' · '.($case->employee->jobTitle?->name ?? $case->employee->department?->name),
])

@section('title', __('onboarding.offboarding.of', ['name' => $case->employee->name]))

@section('page-actions')
    @can('view', $case->employee)
        <x-button variant="secondary" :href="route('admin.employees.show', $case->employee)" icon="user">{{ __('onboarding.common.employee_profile') }}</x-button>
    @endcan
    @if ($case->isOpen() && $canCancel)
        <form method="POST" action="{{ route('admin.offboarding.cancel', $case) }}" data-confirm="{{ __('onboarding.offboarding.cancel_confirm') }}" data-confirm-tone="warning">
            @csrf
            <x-button variant="ghost" icon="x">{{ __('onboarding.offboarding.cancel') }}</x-button>
        </form>
    @endif
@endsection

@section('content')
    @php($pending = $case->items->where('status', App\Enums\TaskStatus::Pending)->count())
    @if ($case->status === App\Enums\OffboardingStatus::Completed)
        <x-alert tone="success" :title="__('onboarding.offboarding.completed_title', ['date' => $case->completed_at?->toDateString(), 'name' => $case->completedBy?->name])">
            {{ $case->last_working_date->isFuture() ? __('onboarding.offboarding.completed_scheduled', ['date' => $case->last_working_date->toDateString()]) : __('onboarding.offboarding.completed_done') }}
        </x-alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-card :title="__('onboarding.offboarding.handover')" padding="none">
                <x-slot:actions>
                    <div class="w-40">@include('admin.lifecycle._progress', ['value' => $case->progress(), 'label' => __('onboarding.offboarding.handover')])</div>
                </x-slot:actions>
                <ul class="divide-y divide-border-subtle">
                    @forelse ($case->items as $item)
                        <li class="flex flex-col gap-2 px-5 py-3 md:flex-row md:items-center">
                            <div class="min-w-0 flex-1">
                                <p @class(['text-label-md', 'text-text-tertiary line-through' => $item->status->isFinished()])>{{ $item->title }}</p>
                                <p class="text-caption-md text-text-tertiary">{{ $item->category->label() }} · {{ $item->assignee->label() }}
                                    @if ($item->completedBy) · {{ $item->completedBy->name }} <span dir="ltr">{{ $item->completed_at?->format('Y-m-d') }}</span>@endif</p>
                                @if ($item->note)
                                    <p class="text-body-sm text-text-secondary">{{ $item->note }}</p>
                                @endif
                                @if ($item->itemable_type === 'asset_assignment' && ($clearance = $item->itemable) && ($clearanceAsset = $clearance->asset()->withoutGlobalScopes()->first()))
                                    {{-- Asset clearance (Phase 8a): completed by recording the return on the asset. --}}
                                    <div class="mt-1 flex flex-wrap items-center gap-2" data-asset-clearance="{{ $clearance->id }}">
                                        <x-badge :tone="$clearance->status->tone()">{{ $clearance->status->label() }}</x-badge>
                                        @can('view', $clearanceAsset)
                                            <a class="text-body-sm text-text-link hover:underline" href="{{ route('admin.assets.show', $clearanceAsset) }}">{{ __('assets.clearance.open_asset') }}</a>
                                        @endcan
                                        @if ($clearance->isActive())
                                            @can('update', $clearance)
                                                <x-button size="sm" variant="secondary" icon="check" :href="route('admin.assets.show', $clearanceAsset).'#custodian'">{{ __('assets.clearance.record_return') }}</x-button>
                                            @endcan
                                        @endif
                                    </div>
                                @endif
                                @if ($item->itemable_type === $case->getMorphClass() && $item->itemable_id === $case->id)
                                    @if ($settlementRun)
                                        @can('view', $settlementRun)
                                            <a class="text-body-sm text-text-link hover:underline" href="{{ route('admin.payroll-runs.show', $settlementRun) }}"><span dir="ltr">{{ $settlementRun->reference }}</span> · {{ $settlementRun->status->label() }}</a>
                                        @endcan
                                    @endif
                                    @if (! $settlementRun || in_array($settlementRun->status->value, ['cancelled', 'reversed'], true))
                                        @can('create', App\Models\PayrollRun::class)
                                            <x-button size="sm" variant="secondary" icon="coins" :href="route('admin.offboarding.final-settlement.create', $case)">{{ __('payroll_runs.final_settlement.prepare') }}</x-button>
                                        @endcan
                                    @endif
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <x-badge :tone="$item->status->tone()">{{ $item->status === App\Enums\TaskStatus::Skipped ? __('onboarding.offboarding.waived') : $item->status->label() }}</x-badge>
                                @if ($canUpdate && $case->isOpen())
                                    @include('admin.lifecycle._task-status-form', ['action' => route('admin.offboarding.items.update', [$case, $item])])
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-body-md text-text-tertiary">{{ __('onboarding.offboarding.no_items') }}</li>
                    @endforelse
                </ul>
                @if ($canUpdate && $case->isOpen())
                    <x-slot:footer>
                        <form method="POST" action="{{ route('admin.offboarding.items.store', $case) }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-[1fr_10rem_10rem_auto] lg:items-end" data-validate>
                            @csrf
                            <x-form.input name="title" :label="__('onboarding.fields.title')" required maxlength="200" />
                            <x-form.select name="category" :label="__('onboarding.fields.category')" required>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->value }}">{{ $category->label() }}</option>
                                @endforeach
                            </x-form.select>
                            <x-form.select name="assignee" :label="__('onboarding.fields.assignee')" required>
                                @foreach ($assignees as $assignee)
                                    <option value="{{ $assignee->value }}">{{ $assignee->label() }}</option>
                                @endforeach
                            </x-form.select>
                            <x-button variant="secondary" icon="plus">{{ __('onboarding.actions.add_item') }}</x-button>
                        </form>
                    </x-slot:footer>
                @endif
            </x-card>

            <x-card :title="__('onboarding.exit.title')" :subtitle="$case->exit_interview_at ? __('onboarding.exit.recorded', ['date' => $case->exit_interview_at->format('Y-m-d'), 'name' => $case->exitInterviewer?->name]) : __('onboarding.exit.not_recorded')">
                @php($answers = $case->exit_interview_answers ?? [])
                @if ($canUpdate && $case->isOpen())
                    <form method="POST" action="{{ route('admin.offboarding.exit-interview', $case) }}" class="grid gap-4 md:grid-cols-2" data-validate>
                        @csrf
                        @foreach ($questions as $key => $question)
                            @if ($question['type'] === 'choice')
                                <x-form.select :name="'answers['.$key.']'" :label="__('onboarding.exit.questions.'.$key)">
                                    <option value="">—</option>
                                    @foreach ($question['options'] as $option)
                                        <option value="{{ $option }}" @selected(old('answers.'.$key, $answers[$key] ?? null) === $option)>{{ __('onboarding.exit.options.'.$option) }}</option>
                                    @endforeach
                                </x-form.select>
                            @elseif ($question['type'] === 'rating')
                                <x-form.select :name="'answers['.$key.']'" :label="__('onboarding.exit.questions.'.$key)">
                                    <option value="">—</option>
                                    @for ($rating = 1; $rating <= 5; $rating++)
                                        <option value="{{ $rating }}" @selected((string) old('answers.'.$key, $answers[$key] ?? null) === (string) $rating)>{{ $rating }} — {{ __('onboarding.exit.ratings.'.$rating) }}</option>
                                    @endfor
                                </x-form.select>
                            @else
                                <x-form.select :name="'answers['.$key.']'" :label="__('onboarding.exit.questions.'.$key)">
                                    <option value="">—</option>
                                    <option value="1" @selected((string) old('answers.'.$key, isset($answers[$key]) ? (int) (bool) $answers[$key] : null) === '1')>{{ __('app.common.yes') }}</option>
                                    <option value="0" @selected((string) old('answers.'.$key, isset($answers[$key]) ? (int) (bool) $answers[$key] : null) === '0')>{{ __('app.common.no') }}</option>
                                </x-form.select>
                            @endif
                        @endforeach
                        <div class="md:col-span-2">
                            <x-form.textarea name="notes" :label="__('onboarding.exit.notes')" rows="4" maxlength="5000">{{ old('notes', $case->exit_interview_notes) }}</x-form.textarea>
                        </div>
                        <div><x-button variant="secondary" icon="check">{{ __('onboarding.exit.save') }}</x-button></div>
                    </form>
                @elseif ($case->exit_interview_at)
                    <x-detail-list>
                        @foreach ($questions as $key => $question)
                            @isset($answers[$key])
                                <x-detail-item :label="__('onboarding.exit.questions.'.$key)">
                                    {{ match ($question['type']) { 'choice' => __('onboarding.exit.options.'.$answers[$key]), 'boolean' => $answers[$key] ? __('app.common.yes') : __('app.common.no'), default => $answers[$key].' / 5' } }}
                                </x-detail-item>
                            @endisset
                        @endforeach
                    </x-detail-list>
                    @if ($case->exit_interview_notes)
                        <p class="mt-4 whitespace-pre-line text-body-sm text-text-secondary">{{ $case->exit_interview_notes }}</p>
                    @endif
                @endif
            </x-card>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('onboarding.offboarding.details')">
                <div class="mb-3 flex flex-wrap gap-2">
                    @include('admin.recruitment._badge', ['value' => $case->status])
                    <x-badge>{{ $case->source->label() }}</x-badge>
                </div>
                @if ($canUpdate && $case->isOpen())
                    <form method="POST" action="{{ route('admin.offboarding.update', $case) }}" class="grid gap-3" data-validate>
                        @csrf
                        @method('PUT')
                        @include('admin.lifecycle.offboarding._case-fields')
                        <div><x-button variant="secondary">{{ __('app.actions.save') }}</x-button></div>
                    </form>
                @else
                    <x-detail-list :columns="1">
                        <x-detail-item :label="__('onboarding.fields.type')">{{ $case->type->label() }}</x-detail-item>
                        <x-detail-item :label="__('onboarding.fields.notice_date')"><span dir="ltr">{{ $case->notice_date?->toDateString() ?? '—' }}</span></x-detail-item>
                        <x-detail-item :label="__('onboarding.fields.last_working_date')"><span dir="ltr">{{ $case->last_working_date->toDateString() }}</span></x-detail-item>
                        <x-detail-item :label="__('onboarding.fields.eligible_for_rehire')">{{ $case->eligible_for_rehire ? __('app.common.yes') : __('app.common.no') }}</x-detail-item>
                        <x-detail-item :label="__('onboarding.fields.reason')">{{ $case->reason ?? '—' }}</x-detail-item>
                    </x-detail-list>
                @endif
            </x-card>

            <x-card :title="__('onboarding.offboarding.accounts')">
                <ul class="flex flex-col gap-2">
                    @forelse ($linkedAccounts as $account)
                        <li class="flex items-center justify-between gap-2 text-body-sm">
                            <span>{{ $account->email }} <span class="text-text-tertiary">({{ $account->account_type->label() }})</span></span>
                            <x-badge :tone="$account->isActive() ? 'success' : 'neutral'">{{ $account->status->label() }}</x-badge>
                        </li>
                    @empty
                        <li class="text-body-sm text-text-tertiary">{{ __('onboarding.offboarding.no_accounts') }}</li>
                    @endforelse
                </ul>
                <p class="mt-3 text-caption-md text-text-tertiary">{{ __('onboarding.offboarding.accounts_help') }}</p>
            </x-card>

            @if ($case->isOpen() && $canComplete)
                <x-card :title="__('onboarding.offboarding.complete')">
                    @if ($pending > 0)
                        <x-alert tone="warning">{{ trans_choice('onboarding.offboarding.pending_items', $pending, ['count' => $pending]) }}</x-alert>
                    @else
                        <p class="mb-3 text-body-sm text-text-secondary">{{ __('onboarding.offboarding.complete_help', ['date' => $case->last_working_date->toDateString()]) }}</p>
                        <form method="POST" action="{{ route('admin.offboarding.complete', $case) }}"
                            data-confirm="{{ __('onboarding.offboarding.complete_confirm', ['name' => $case->employee->name, 'date' => $case->last_working_date->toDateString()]) }}" data-confirm-tone="danger">
                            @csrf
                            <x-button variant="danger" icon="log-out">{{ __('onboarding.offboarding.complete') }}</x-button>
                        </form>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
@endsection
