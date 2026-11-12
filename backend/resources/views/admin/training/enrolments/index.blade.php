@extends('layouts.admin', ['subtitle' => __('training.enrolments.subtitle')])

@section('title', __('training.enrolments.title'))

@section('page-actions')
    @can('export', App\Models\TrainingEnrolment::class)
        <x-button variant="ghost" icon="download" :href="route('admin.training-enrolments.export', request()->query())">{{ __('training.common.export_xlsx') }}</x-button>
        <x-button variant="ghost" icon="download" :href="route('admin.training-enrolments.export', [...request()->query(), 'format' => 'csv'])">{{ __('training.common.export_csv') }}</x-button>
    @endcan
@endsection

@section('content')
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('training.enrolments.my_approvals')" :value="$counts['mine']" icon="check-circle" :href="route('admin.training-enrolments.index', ['mine' => 1])" />
        <x-stat-card :label="__('training.enrolments.awaiting')" :value="$counts['pending']" icon="inbox" :href="route('admin.training-enrolments.index', ['status' => 'pending'])" />
        <x-stat-card :label="__('training.enrolments.enrolled_now')" :value="$counts['enrolled']" icon="users" :href="route('admin.training-enrolments.index', ['status' => 'enrolled'])" />
        <x-stat-card :label="__('training.enrolments.completed_year')" :value="$counts['completed']" icon="award" :href="route('admin.training-enrolments.index', ['status' => 'completed', 'year' => now()->year])" />
    </div>

    <x-filter-bar :action="route('admin.training-enrolments.index')" :reset="route('admin.training-enrolments.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="status" :label="__('training.fields.status')" hide-label class="md:w-40">
            <option value="">{{ __('training.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="source" :label="__('training.fields.source')" hide-label class="md:w-40">
            <option value="">{{ __('training.fields.source') }}: {{ __('app.common.all') }}</option>
            @foreach ($sources as $source)
                <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ $source->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="training_course_id" :label="__('training.fields.course')" hide-label class="md:w-52">
            <option value="">{{ __('training.fields.course') }}: {{ __('app.common.all') }}</option>
            @foreach ($courses as $course)
                <option value="{{ $course->id }}" @selected((string) ($filters['training_course_id'] ?? '') === (string) $course->id)>{{ $course->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="department_id" :label="__('training.fields.department')" hide-label class="md:w-48">
            <option value="">{{ __('training.fields.department') }}: {{ __('app.common.all') }}</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->name }}</option>
            @endforeach
        </x-form.select>
        @if (! empty($filters['mine']))
            <input type="hidden" name="mine" value="1">
        @endif
    </x-filter-bar>

    <x-table :caption="__('training.enrolments.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('training.fields.employee') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('training.fields.session') }}</x-table.th>
                <x-table.th sort="start_date">{{ __('training.fields.start_date') }}</x-table.th>
                <x-table.th>{{ __('training.fields.source') }}</x-table.th>
                <x-table.th sort="cost">{{ __('training.fields.cost') }}</x-table.th>
                <x-table.th>{{ __('training.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($enrolments as $enrolment)
            <x-table.row :data-href="route('admin.training-enrolments.show', $enrolment)">
                <x-table.td :label="__('training.fields.employee')">
                    <a class="text-label-md text-text-link hover:underline" href="{{ route('admin.training-enrolments.show', $enrolment) }}">{{ $enrolment->employee->label() }}</a>
                </x-table.td>
                <x-table.td :label="__('training.fields.session')">
                    {{ $enrolment->session->course->name }}
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $enrolment->session->reference }}</span>
                </x-table.td>
                <x-table.td :label="__('training.fields.start_date')" class="whitespace-nowrap"><span dir="ltr">{{ $enrolment->session->start_date->toDateString() }}</span></x-table.td>
                <x-table.td :label="__('training.fields.source')">{{ $enrolment->source->label() }}</x-table.td>
                <x-table.td :label="__('training.fields.cost')">@include('admin.payroll._money', ['amount' => $enrolment->cost])</x-table.td>
                <x-table.td :label="__('training.fields.status')">
                    <x-badge :tone="$enrolment->status->tone()" dot>{{ $enrolment->status->label() }}</x-badge>
                    @if ($enrolment->isPending() && $enrolment->latestApprovalRequest?->currentStep)
                        <span class="block text-caption-md text-text-tertiary">{{ __('training.common.at_step', ['step' => $enrolment->latestApprovalRequest->currentStep->name]) }}</span>
                    @endif
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="inbox" :title="__('training.enrolments.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$enrolments" />
        </x-slot:footer>
    </x-table>
@endsection
