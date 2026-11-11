{{-- Employment tab. Variables: $employee, $departmentHead, $branchManager, $directReports, $currentContract, $pending. --}}
@php($none = __('structure.common.not_set'))
@if ($pending->isNotEmpty())
    <x-alert tone="info" :title="__('employees.profile.scheduled_title')" data-region="scheduled">
        <ul class="mt-1 flex flex-col gap-2">
            @foreach ($pending as $change)
                <li class="flex flex-wrap items-center justify-between gap-2">
                    <span>
                        <span dir="ltr">{{ $change->effective_date->toDateString() }}</span> — {{ $change->change_type->label() }}:
                        {{ $change->department?->name }}@if ($change->position) · {{ $change->position->name }}@endif
                        @if ($change->reason) ({{ $change->reason }})@endif
                    </span>
                    @can($change->change_type === App\Enums\AssignmentChangeType::Termination ? 'manage' : 'update', $employee)
                        <form method="POST" action="{{ route('admin.employees.assignments.destroy', [$employee, $change]) }}" data-confirm="{{ __('employees.profile.confirm_cancel_change') }}">
                            @csrf
                            @method('DELETE')
                            <x-button variant="ghost" size="sm" icon="x">{{ __('employees.profile.cancel_change') }}</x-button>
                        </form>
                    @endcan
                </li>
            @endforeach
        </ul>
    </x-alert>
@endif

<div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
    <x-card :title="__('employees.form.employment')" class="lg:col-span-2">
        <x-detail-list>
            <x-detail-item :label="__('employees.fields.branch_id')">{{ $employee->branch?->name }}</x-detail-item>
            <x-detail-item :label="__('employees.fields.department_id')">{{ $employee->department?->name }}</x-detail-item>
            <x-detail-item :label="__('employees.fields.team_id')">{{ $employee->team?->name ?? $none }}</x-detail-item>
            <x-detail-item :label="__('employees.fields.position_id')">{{ $employee->position?->name ?? $none }}</x-detail-item>
            <x-detail-item :label="__('employees.fields.job_title_id')">{{ $employee->jobTitle?->name ?? $none }}</x-detail-item>
            <x-detail-item :label="__('employees.fields.job_grade_id')">{{ $employee->jobGrade?->name ?? $none }}</x-detail-item>
            <x-detail-item :label="__('employees.fields.employment_type_id')">{{ $employee->employmentType?->name }}</x-detail-item>
            <x-detail-item :label="__('employees.fields.cost_center_id')">{{ $employee->costCenter ? $employee->costCenter->code.' · '.$employee->costCenter->name : $none }}</x-detail-item>
            <x-detail-item :label="__('employees.fields.work_location_id')">{{ $employee->workLocation?->name ?? $none }}</x-detail-item>
            <x-detail-item :label="__('employees.fields.status')">@include('admin.employees._status', ['status' => $employee->status])</x-detail-item>
            <x-detail-item :label="__('employees.fields.hire_date')"><span dir="ltr">{{ $employee->hire_date?->toDateString() }}</span></x-detail-item>
            <x-detail-item :label="__('employees.fields.original_hire_date')"><span dir="ltr">{{ $employee->original_hire_date?->toDateString() }}</span></x-detail-item>
            <x-detail-item :label="__('employees.fields.probation_end_date')"><span dir="ltr">{{ $employee->probation_end_date?->toDateString() ?? $none }}</span></x-detail-item>
            <x-detail-item :label="__('employees.profile.contract_period')">
                @if ($currentContract)
                    {{ $currentContract->contract_type->label() }} · <span dir="ltr">{{ $currentContract->start_date->toDateString() }} → {{ $currentContract->end_date?->toDateString() ?? __('employees.contracts.open_ended') }}</span>
                @else
                    {{ __('employees.contracts.none_current') }}
                @endif
            </x-detail-item>
        </x-detail-list>
    </x-card>

    <div class="flex flex-col gap-4 lg:gap-6">
        <x-card :title="__('employees.profile.reporting')">
            <x-detail-list :columns="1">
                @foreach (['manager_id' => $employee->manager, 'department_head' => $departmentHead, 'branch_manager' => $branchManager] as $label => $person)
                    <x-detail-item :label="__('employees.profile.'.$label)">
                        @if ($person)
                            @can('view', $person)
                                <a href="{{ route('admin.employees.show', $person) }}" class="text-text-link hover:underline">{{ $person->name }}</a>
                            @else
                                {{ $person->name }}
                            @endcan
                        @else
                            {{ $none }}
                        @endif
                    </x-detail-item>
                @endforeach
            </x-detail-list>
        </x-card>

        <x-card :title="__('employees.profile.direct_reports')" :subtitle="trans_choice('employees.profile.people', $directReports->count(), ['count' => $directReports->count()])">
            @forelse ($directReports as $report)
                <a href="{{ route('admin.employees.show', $report) }}" class="flex items-center gap-3 border-b border-border-subtle py-2 last:border-0 hover:text-text-link">
                    <x-avatar :name="$report->name" size="sm" />
                    <span class="min-w-0">
                        <span class="block truncate text-label-md">{{ $report->name }}</span>
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $report->employee_number }}</span>
                    </span>
                </a>
            @empty
                <p class="text-body-sm text-text-tertiary">{{ __('employees.profile.no_reports') }}</p>
            @endforelse
        </x-card>
    </div>
</div>
