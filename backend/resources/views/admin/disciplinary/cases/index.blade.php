@extends('layouts.admin', ['subtitle' => $fullAccess ? __('disciplinary.cases.subtitle') : __('disciplinary.cases.subtitle_reporter')])

@section('title', __('disciplinary.cases.title'))

@section('page-actions')
    @can('export', App\Models\DisciplinaryCase::class)
        <x-button variant="ghost" icon="download" :href="route('admin.disciplinary-cases.export', request()->query())">{{ __('disciplinary.cases.export') }}</x-button>
    @endcan
    @can('create', App\Models\DisciplinaryCase::class)
        <x-button icon="plus" :href="route('admin.disciplinary-cases.create')">{{ __('disciplinary.cases.create') }}</x-button>
    @endcan
@endsection

@section('content')
    @if ($fullAccess)
        <x-alert tone="warning">{{ __('disciplinary.restricted') }}</x-alert>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card :label="__('disciplinary.cases.open')" :value="$counts['open']" icon="inbox" :href="route('admin.disciplinary-cases.index', ['status' => 'investigating'])" />
            <x-stat-card :label="__('disciplinary.cases.pending')" :value="$counts['pending']" icon="clock" :href="route('admin.disciplinary-cases.index', ['status' => 'pending_approval'])" />
            <x-stat-card :label="__('disciplinary.cases.awaiting_response')" :value="$counts['awaiting_response']" icon="mail" :href="route('admin.disciplinary-cases.index', ['status' => 'action_issued'])" />
            <x-stat-card :label="__('disciplinary.cases.active_warnings')" :value="$counts['active_warnings']" icon="alert-triangle" />
        </div>
    @endif

    <x-filter-bar :action="route('admin.disciplinary-cases.index')" :reset="route('admin.disciplinary-cases.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="status" :label="__('disciplinary.fields.status')" hide-label class="md:w-52">
            <option value="">{{ __('disciplinary.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="violation_type_id" :label="__('disciplinary.fields.violation_type')" hide-label class="md:w-52">
            <option value="">{{ __('disciplinary.fields.violation_type') }}: {{ __('app.common.all') }}</option>
            @foreach ($violationTypes as $type)
                <option value="{{ $type->id }}" @selected((string) ($filters['violation_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
            @endforeach
        </x-form.select>
        @if ($departments->isNotEmpty())
            <x-form.select name="department_id" :label="__('disciplinary.fields.department')" hide-label class="md:w-48">
                <option value="">{{ __('disciplinary.fields.department') }}: {{ __('app.common.all') }}</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </x-form.select>
        @endif
        <x-form.input type="date" name="from" :label="__('disciplinary.fields.from')" hide-label :value="$filters['from'] ?? ''" class="md:w-40" />
        <x-form.input type="date" name="to" :label="__('disciplinary.fields.to')" hide-label :value="$filters['to'] ?? ''" class="md:w-40" />
    </x-filter-bar>

    <x-table :caption="__('disciplinary.cases.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="reference">{{ __('disciplinary.fields.reference') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('disciplinary.fields.employee') }}</x-table.th>
                <x-table.th>{{ __('disciplinary.fields.violation_type') }}</x-table.th>
                <x-table.th sort="incident_date">{{ __('disciplinary.fields.incident_date') }}</x-table.th>
                <x-table.th sort="status">{{ __('disciplinary.fields.status') }}</x-table.th>
                <x-table.th>{{ __('disciplinary.cases.outcome') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($cases as $case)
            <x-table.row :data-href="route('admin.disciplinary-cases.show', $case)">
                <x-table.td :label="__('disciplinary.fields.reference')" class="whitespace-nowrap">
                    <a href="{{ route('admin.disciplinary-cases.show', $case) }}" class="text-label-md text-text-link hover:underline" dir="ltr">{{ $case->reference }}</a>
                </x-table.td>
                <x-table.td :label="__('disciplinary.fields.employee')">{{ $case->employee->label() }}</x-table.td>
                <x-table.td :label="__('disciplinary.fields.violation_type')">
                    {{ $case->violationType->name }}
                    <span class="block"><x-badge :tone="$case->violationType->severity->tone()">{{ $case->violationType->severity->label() }}</x-badge></span>
                </x-table.td>
                <x-table.td :label="__('disciplinary.fields.incident_date')" class="whitespace-nowrap"><span dir="ltr">{{ $case->incident_date->toDateString() }}</span></x-table.td>
                <x-table.td :label="__('disciplinary.fields.status')">@include('admin.disciplinary.cases._status', ['status' => $case->status])</x-table.td>
                <x-table.td :label="__('disciplinary.cases.outcome')">
                    @if ($case->latestAction)
                        @include('admin.disciplinary.cases._action', ['action' => $case->latestAction])
                    @else
                        <span class="text-text-tertiary">—</span>
                    @endif
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="alert-triangle" :title="__('disciplinary.cases.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$cases" />
        </x-slot:footer>
    </x-table>
@endsection
