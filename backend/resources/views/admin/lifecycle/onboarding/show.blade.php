@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('onboarding.onboarding.title'), 'url' => route('admin.onboarding.index')]],
    'subtitle' => $onboarding->employee->employee_number.' · '.($onboarding->employee->jobTitle?->name ?? $onboarding->employee->department?->name),
])

@section('title', __('onboarding.onboarding.of', ['name' => $onboarding->employee->name]))

@section('page-actions')
    @can('view', $onboarding->employee)
        <x-button variant="secondary" :href="route('admin.employees.show', $onboarding->employee)" icon="user">{{ __('onboarding.common.employee_profile') }}</x-button>
    @endcan
    @if ($onboarding->status !== App\Enums\OnboardingStatus::Cancelled)
        @can('delete', $onboarding)
            <form method="POST" action="{{ route('admin.onboarding.cancel', $onboarding) }}" data-confirm="{{ __('onboarding.onboarding.cancel_confirm') }}" data-confirm-tone="warning">
                @csrf
                <x-button variant="ghost" icon="x">{{ __('onboarding.onboarding.cancel') }}</x-button>
            </form>
        @endcan
    @endif
@endsection

@section('content')
    <x-card>
        <div class="flex flex-col gap-4 md:flex-row md:items-center">
            <div class="flex flex-wrap items-center gap-2">
                @include('admin.recruitment._badge', ['value' => $onboarding->status])
                <span class="text-body-sm text-text-secondary">{{ __('onboarding.fields.start_date') }}: <span dir="ltr">{{ $onboarding->start_date->toDateString() }}</span></span>
                @if ($onboarding->template)
                    <span class="text-body-sm text-text-secondary">· {{ $onboarding->template->name }}</span>
                @endif
                @if ($onboarding->application)
                    <span class="text-body-sm text-text-secondary">· {{ __('onboarding.onboarding.from_vacancy', ['reference' => $onboarding->application->vacancy->reference]) }}</span>
                @endif
            </div>
            <div class="md:ms-auto md:w-72">@include('admin.lifecycle._progress', ['value' => $onboarding->progress(), 'label' => __('onboarding.onboarding.progress')])</div>
        </div>
    </x-card>

    <x-card :title="__('onboarding.onboarding.checklist')" padding="none">
        <ul class="divide-y divide-border-subtle">
            @forelse ($onboarding->tasks as $task)
                <li class="flex flex-col gap-2 px-5 py-3 md:flex-row md:items-center">
                    <div class="min-w-0 flex-1">
                        <p @class(['text-label-md', 'text-text-tertiary line-through' => $task->status->isFinished()])>{{ $task->title }}</p>
                        <p class="text-caption-md text-text-tertiary">
                            {{ $task->assignee->label() }}
                            @if ($task->due_date) · {{ __('onboarding.fields.due_date') }} <span dir="ltr">{{ $task->due_date->toDateString() }}</span>@endif
                            @if ($task->completedBy) · {{ $task->status->label() }}: {{ $task->completedBy->name }} <span dir="ltr">{{ $task->completed_at?->format('Y-m-d') }}</span>@endif
                        </p>
                        @if ($task->description)
                            <p class="text-body-sm text-text-secondary">{{ $task->description }}</p>
                        @endif
                        @if ($task->action && ! $task->status->isFinished())
                            @php $target = $task->action->target($onboarding->employee_id); @endphp
                            @if (Route::has($target['route']) && auth()->user()->can($target['ability']))
                                <a href="{{ route($target['route'], $target['parameters']) }}" class="inline-flex items-center gap-1 text-label-md text-text-link hover:underline" data-task-action="{{ $task->action->value }}">
                                    {{ $task->action->label() }} <x-icon name="arrow-right" size="sm" flip />
                                </a>
                            @endif
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($task->isOverdue())
                            <x-badge tone="danger">{{ __('onboarding.common.overdue') }}</x-badge>
                        @else
                            <x-badge :tone="$task->status->tone()">{{ $task->status->label() }}</x-badge>
                        @endif
                        @if ($canUpdate && $onboarding->status !== App\Enums\OnboardingStatus::Cancelled)
                            @include('admin.lifecycle._task-status-form', ['item' => $task, 'action' => route('admin.onboarding.tasks.update', [$onboarding, $task])])
                            <form method="POST" action="{{ route('admin.onboarding.tasks.destroy', [$onboarding, $task]) }}" data-confirm="{{ __('onboarding.onboarding.remove_task_confirm') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-button icon="trash" type="submit" size="sm" :label="__('onboarding.actions.remove')" />
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="px-5 py-6 text-body-md text-text-tertiary">{{ __('onboarding.onboarding.no_tasks') }}</li>
            @endforelse
        </ul>
        @if ($canUpdate && $onboarding->status !== App\Enums\OnboardingStatus::Cancelled)
            <x-slot:footer>
                <form method="POST" action="{{ route('admin.onboarding.tasks.store', $onboarding) }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-[1fr_1fr_12rem_10rem_auto] lg:items-end" data-validate>
                    @csrf
                    <x-form.input name="title_ar" :label="__('onboarding.fields.title_ar')" required maxlength="200" dir="rtl" />
                    <x-form.input name="title_en" :label="__('onboarding.fields.title_en')" required maxlength="200" dir="ltr" />
                    <x-form.select name="assignee" :label="__('onboarding.fields.assignee')" required>
                        @foreach ($assignees as $assignee)
                            <option value="{{ $assignee->value }}">{{ $assignee->label() }}</option>
                        @endforeach
                    </x-form.select>
                    <x-form.input name="due_date" type="date" :label="__('onboarding.fields.due_date')" />
                    <x-button variant="secondary" icon="plus">{{ __('onboarding.actions.add_task') }}</x-button>
                </form>
            </x-slot:footer>
        @endif
    </x-card>
@endsection
