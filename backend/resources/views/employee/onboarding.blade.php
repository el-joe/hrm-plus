@extends('layouts.employee')

@section('title', __('onboarding.portal.title'))

@section('content')
    @if (! $onboarding)
        <x-card>
            <x-empty-state icon="check-circle" :title="__('onboarding.portal.none')" :description="__('onboarding.portal.none_help')" />
        </x-card>
    @else
        <x-card>
            <div class="flex flex-col gap-3 md:flex-row md:items-center">
                <div>
                    <p class="text-h5">{{ __('onboarding.portal.welcome', ['name' => $employee->name]) }}</p>
                    <p class="text-body-sm text-text-secondary">{{ __('onboarding.fields.start_date') }}: <x-date :value="$onboarding->start_date" /> · {{ $onboarding->status->label() }}</p>
                </div>
                <div class="md:ms-auto md:w-72">@include('admin.lifecycle._progress', ['value' => $onboarding->progress(), 'label' => __('onboarding.onboarding.progress')])</div>
            </div>
        </x-card>

        @php($own = $onboarding->tasks->where('assignee', App\Enums\TaskAssignee::Employee))
        <x-card :title="__('onboarding.portal.my_tasks')" padding="none">
            <ul class="divide-y divide-border-subtle">
                @forelse ($own as $task)
                    <li class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center">
                        <div class="min-w-0 flex-1">
                            <p @class(['text-label-md', 'text-text-tertiary line-through' => $task->status->isFinished()])>{{ $task->title }}</p>
                            @if ($task->description)
                                <p class="text-body-sm text-text-secondary">{{ $task->description }}</p>
                            @endif
                            @if ($task->due_date)
                                <p class="text-caption-md text-text-tertiary">{{ __('onboarding.fields.due_date') }} <x-date :value="$task->due_date" /></p>
                            @endif
                        </div>
                        @if ($task->isOverdue())
                            <x-badge tone="danger">{{ __('onboarding.common.overdue') }}</x-badge>
                        @endif
                        @if ($onboarding->status !== App\Enums\OnboardingStatus::Cancelled)
                            <form method="POST" action="{{ route('portal.onboarding.tasks.update', $task->id) }}">
                                @csrf
                                @method('PATCH')
                                @if ($task->status->isFinished())
                                    <input type="hidden" name="status" value="pending">
                                    <x-button size="sm" variant="ghost" icon="history">{{ __('onboarding.actions.reopen') }}</x-button>
                                @else
                                    <input type="hidden" name="status" value="done">
                                    <x-button size="sm" icon="check">{{ __('onboarding.portal.mark_done') }}</x-button>
                                @endif
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-6 text-body-md text-text-tertiary">{{ __('onboarding.portal.no_own_tasks') }}</li>
                @endforelse
            </ul>
        </x-card>

        @php($others = $onboarding->tasks->where('assignee', '!=', App\Enums\TaskAssignee::Employee))
        @if ($others->isNotEmpty())
            <x-card :title="__('onboarding.portal.company_tasks')" :subtitle="__('onboarding.portal.company_tasks_help')" padding="none">
                <ul class="divide-y divide-border-subtle">
                    @foreach ($others as $task)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="min-w-0 flex-1 text-body-md">{{ $task->title }} <span class="text-caption-md text-text-tertiary">· {{ $task->assignee->label() }}</span></span>
                            <x-badge :tone="$task->status->tone()">{{ $task->status->label() }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    @endif
@endsection
