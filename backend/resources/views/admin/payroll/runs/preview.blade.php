@extends('layouts.admin', ['breadcrumbs' => [['label' => __('payroll_runs.runs.title'), 'url' => route('admin.payroll-runs.index')]], 'subtitle' => $period->name.' · '.$type->label()])

@section('title', __('payroll_runs.wizard.preview_title'))

@section('content')
    @include('admin.payroll.runs._wizard', ['current' => 2])

    <x-alert tone="info" :title="__('payroll_runs.wizard.scope_summary')">{{ $summary }}</x-alert>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <x-stat-card :label="__('payroll_runs.preview.included')" :value="$preview['summary']['included']" icon="users" />
        <x-stat-card :label="__('payroll_runs.preview.excluded')" :value="$preview['summary']['excluded']" icon="x-circle" />
        <x-stat-card :label="__('payroll_runs.preview.branches')" :value="$preview['summary']['branches']" icon="map-pin" />
        <x-stat-card :label="__('payroll_runs.preview.movers')" :value="$preview['summary']['moved']" icon="arrow-right" />
        <x-stat-card :label="__('payroll_runs.preview.no_salary')" :value="$preview['summary']['no_salary']" icon="alert-triangle" />
    </div>

    <x-card :title="__('payroll_runs.preview.included_title')" :subtitle="__('payroll_runs.preview.included_help')" padding="none" data-region="included">
        <x-table :caption="__('payroll_runs.preview.included_title')">
            <x-slot:head>
                <tr>
                    <x-table.th class="min-w-48">{{ __('payroll_runs.preview.employee') }}</x-table.th>
                    <x-table.th>{{ __('payroll_runs.preview.branch') }}</x-table.th>
                    <x-table.th>{{ __('payroll_runs.preview.department') }}</x-table.th>
                    <x-table.th>{{ __('payroll_runs.preview.notes') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($preview['included'] as $row)
                <x-table.row>
                    <x-table.td :label="__('payroll_runs.preview.employee')"><span dir="ltr">{{ $row['employee_number'] }}</span> · {{ $row['name'] }}</x-table.td>
                    <x-table.td :label="__('payroll_runs.preview.branch')">{{ $names['branches'][$row['branch_id']] ?? '—' }}</x-table.td>
                    <x-table.td :label="__('payroll_runs.preview.department')">{{ $names['departments'][$row['department_id']] ?? '—' }}</x-table.td>
                    <x-table.td :label="__('payroll_runs.preview.notes')">
                        <div class="flex flex-wrap gap-1">
                            @if ($row['moved'])
                                <x-badge tone="info">{{ __('payroll_runs.preview.moved_in', ['from' => $names['branches'][$row['from_branch_id']] ?? '—', 'date' => $row['moved_on']]) }}</x-badge>
                            @endif
                            @if ($row['joined_in_period']) <x-badge tone="primary">{{ __('payroll_runs.preview.joiner') }}</x-badge> @endif
                            @if ($row['leaving_in_period']) <x-badge tone="warning">{{ __('payroll_runs.preview.leaver') }}</x-badge> @endif
                            @if (! $row['has_salary']) <x-badge tone="danger">{{ __('payroll_runs.preview.missing_salary') }}</x-badge> @endif
                            @if ($row['status'] === 'inactive') <x-badge tone="neutral">{{ __('employees.enums.statuses.inactive') }}</x-badge> @endif
                        </div>
                    </x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="4" icon="users" :title="__('payroll_runs.preview.nobody')" />
            @endforelse
        </x-table>
    </x-card>

    @if ($preview['excluded'] !== [])
        <x-card :title="__('payroll_runs.preview.excluded_title')" :subtitle="__('payroll_runs.preview.excluded_help')" padding="none" data-region="excluded">
            <x-table :caption="__('payroll_runs.preview.excluded_title')">
                <x-slot:head>
                    <tr>
                        <x-table.th class="min-w-48">{{ __('payroll_runs.preview.employee') }}</x-table.th>
                        <x-table.th>{{ __('payroll_runs.preview.branch') }}</x-table.th>
                        <x-table.th>{{ __('payroll_runs.preview.reason') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($preview['excluded'] as $row)
                    <x-table.row>
                        <x-table.td :label="__('payroll_runs.preview.employee')"><span dir="ltr">{{ $row['employee_number'] }}</span> · {{ $row['name'] }}</x-table.td>
                        <x-table.td :label="__('payroll_runs.preview.branch')">{{ $names['branches'][$row['branch_id']] ?? '—' }}</x-table.td>
                        <x-table.td :label="__('payroll_runs.preview.reason')">
                            {{ __('payroll_runs.preview.reasons.'.$row['reason'], ['run' => $row['run_reference'] ?? '', 'branch' => $names['branches'][$row['branch_id']] ?? '—']) }}
                            @if (! empty($row['run_id']))
                                <a class="text-text-link hover:underline" href="{{ route('admin.payroll-runs.show', $row['run_id']) }}" dir="ltr">{{ $row['run_reference'] }}</a>
                            @endif
                        </x-table.td>
                    </x-table.row>
                @endforeach
            </x-table>
        </x-card>
    @endif

    <form method="POST" action="{{ route('admin.payroll-runs.store') }}" class="flex flex-col gap-4">
        @csrf
        <input type="hidden" name="payroll_period_id" value="{{ $period->id }}">
        <input type="hidden" name="type" value="{{ $type->value }}">
        <input type="hidden" name="name" value="{{ $input['name'] ?? '' }}">
        <input type="hidden" name="notes" value="{{ $input['notes'] ?? '' }}">
        <input type="hidden" name="scope[all]" value="{{ $scope['all'] ? 1 : 0 }}">
        @foreach (['branch_ids', 'department_ids', 'team_ids', 'position_ids', 'employee_group_ids', 'employee_ids', 'exclude_employee_ids'] as $key)
            @foreach ($scope[$key] as $id)
                <input type="hidden" name="scope[{{ $key }}][]" value="{{ $id }}">
            @endforeach
        @endforeach
        <x-form.actions>
            <x-button variant="secondary" icon="arrow-left" :href="route('admin.payroll-runs.create', $input)">{{ __('payroll_runs.wizard.back_to_scope') }}</x-button>
            @if ($preview['included'] !== [])
                <x-button icon="check">{{ __('payroll_runs.wizard.create_run', ['count' => $preview['summary']['included']]) }}</x-button>
            @endif
        </x-form.actions>
    </form>
@endsection
