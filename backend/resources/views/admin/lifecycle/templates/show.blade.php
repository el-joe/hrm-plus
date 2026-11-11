@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('onboarding.templates.title'), 'url' => route('admin.onboarding-templates.index')]],
    'subtitle' => collect([$template->position?->name, $template->department?->name, $template->employmentType?->name])->filter()->implode(' · ') ?: __('onboarding.templates.everyone'),
])

@section('title', $template->name)

@section('page-actions')
    <x-button variant="secondary" :href="route('admin.onboarding-templates.edit', $template)" icon="pencil">{{ __('app.actions.edit') }}</x-button>
    <form method="POST" action="{{ route('admin.onboarding-templates.destroy', $template) }}" data-confirm="{{ __('onboarding.templates.delete_confirm') }}">
        @csrf
        @method('DELETE')
        <x-button variant="danger" icon="trash">{{ __('app.actions.delete') }}</x-button>
    </form>
@endsection

@section('content')
    <x-card :title="__('onboarding.templates.tasks')" padding="none">
        <ul class="divide-y divide-border-subtle">
            @forelse ($template->tasks as $task)
                <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-label-md">{{ $task->title }}</p>
                        <p class="text-caption-md text-text-tertiary">{{ $task->assignee->label() }} · {{ trans_choice('onboarding.templates.offset', $task->due_offset_days, ['days' => $task->due_offset_days]) }}</p>
                    </div>
                    <x-icon-button icon="pencil" :href="route('admin.onboarding-templates.tasks.edit', [$template, $task])" :label="__('app.actions.edit')" />
                    <form method="POST" action="{{ route('admin.onboarding-templates.tasks.destroy', [$template, $task]) }}" data-confirm="{{ __('onboarding.onboarding.remove_task_confirm') }}">
                        @csrf
                        @method('DELETE')
                        <x-icon-button icon="trash" type="submit" :label="__('onboarding.actions.remove')" />
                    </form>
                </li>
            @empty
                <li class="px-5 py-6 text-body-md text-text-tertiary">{{ __('onboarding.onboarding.no_tasks') }}</li>
            @endforelse
        </ul>
    </x-card>

    <form method="POST" action="{{ route('admin.onboarding-templates.tasks.store', $template) }}" class="flex flex-col gap-4" data-validate>
        @csrf
        <x-form.section :title="__('onboarding.actions.add_task')" :description="__('onboarding.templates.task_help')">
            @include('admin.lifecycle.templates._task-fields', ['task' => null])
        </x-form.section>
        <div><x-button icon="plus">{{ __('onboarding.actions.add_task') }}</x-button></div>
    </form>
@endsection
