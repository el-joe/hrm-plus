@extends('layouts.admin', [
    'breadcrumbs' => [
        ['label' => __('onboarding.templates.title'), 'url' => route('admin.onboarding-templates.index')],
        ['label' => $template->name, 'url' => route('admin.onboarding-templates.show', $template)],
    ],
])

@section('title', __('onboarding.templates.edit_task'))

@section('content')
    <form method="POST" action="{{ route('admin.onboarding-templates.tasks.update', [$template, $task]) }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @method('PUT')
        <x-form.section :title="__('onboarding.templates.edit_task')" :description="__('onboarding.templates.task_help')">
            @include('admin.lifecycle.templates._task-fields')
            <x-form.input name="sort_order" type="number" min="0" max="9999" :label="__('onboarding.fields.sort_order')" :value="old('sort_order', $task->sort_order)" />
        </x-form.section>
        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.onboarding-templates.show', $template)">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
