@extends('layouts.admin', ['breadcrumbs' => [['label' => __('assets.assignments.title'), 'url' => route('admin.asset-assignments.index')]]])

@section('title', __('assets.assignments.create_title'))

@section('content')
    @if ($task)
        <x-alert tone="info">{{ __('assets.assignments.onboarding_task', ['task' => $task->title]) }}</x-alert>
    @endif

    @if ($assets->isEmpty())
        <x-empty-state icon="laptop" :title="__('assets.assignments.no_available')">
            @can('create', App\Models\Asset::class)
                <x-button icon="plus" :href="route('admin.assets.create')">{{ __('assets.create') }}</x-button>
            @endcan
        </x-empty-state>
    @else
        <form method="POST" action="{{ route('admin.asset-assignments.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
            @csrf
            @if ($task)
                <input type="hidden" name="onboarding_task_id" value="{{ $task->id }}">
            @endif
            <x-form.section :title="__('assets.assignments.create_title')" :description="__('assets.assignments.create_help')">
                <x-form.select name="asset_id" required :label="__('assets.fields.asset_id')">
                    <option value="">—</option>
                    @foreach ($assets->groupBy(fn ($asset) => $asset->category?->name) as $category => $rows)
                        <optgroup label="{{ $category }}">
                            @foreach ($rows as $asset)
                                <option value="{{ $asset->id }}" @selected((string) old('asset_id', $selectedAsset) === (string) $asset->id)>{{ $asset->label() }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </x-form.select>
                <x-form.select name="employee_id" required :label="__('assets.fields.employee')">
                    <option value="">—</option>
                    @foreach ($employees as $employee)
                        @continue($employee->id === auth()->user()->employee_id)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id', $selectedEmployee) === (string) $employee->id)>{{ $employee->label() }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input type="date" name="assigned_on" required :label="__('assets.fields.assigned_on')" :value="old('assigned_on', now()->toDateString())" :max="now()->toDateString()" />
                <x-form.input type="date" name="expected_return_on" :label="__('assets.fields.expected_return_on')" :value="old('expected_return_on')" />
                <x-form.select name="condition_on_issue" required :label="__('assets.fields.condition_on_issue')">
                    @foreach ($conditions as $condition)
                        <option value="{{ $condition->value }}" @selected(old('condition_on_issue', 'good') === $condition->value)>{{ $condition->label() }}</option>
                    @endforeach
                </x-form.select>
                <div class="md:col-span-2">
                    <x-form.textarea name="handover_notes" rows="3" maxlength="2000" :label="__('assets.fields.handover_notes')">{{ old('handover_notes') }}</x-form.textarea>
                </div>
            </x-form.section>
            <x-form.actions>
                <x-button variant="secondary" :href="route('admin.asset-assignments.index')">{{ __('app.actions.cancel') }}</x-button>
                <x-button icon="user-plus">{{ __('assets.show.assign') }}</x-button>
            </x-form.actions>
        </form>
    @endif
@endsection
