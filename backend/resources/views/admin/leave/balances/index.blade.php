@extends('layouts.admin', ['subtitle' => __('leave.balances.subtitle', ['year' => $yearLabel])])

@section('title', __('leave.balances.title'))

@section('page-actions')
    @if (auth()->user()->can('leave_balances.export') || auth()->user()->can('exportLeaveReports'))
        <x-button variant="secondary" icon="download" :href="route('admin.leave-reports.export', ['balances', ...request()->only(['search', 'branch_id', 'department_id']), 'year' => $year, 'format' => 'xlsx'])">{{ __('leave.reports.export_xlsx') }}</x-button>
    @endif
@endsection

@section('content')
    <x-filter-bar :action="route('admin.leave-balances.index')" :reset="route('admin.leave-balances.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="__('leave.common.search_employee')" :value="$filters['search'] ?? ''" class="md:w-52" data-filter-debounce />
        <x-form.input name="year" type="number" :label="__('leave.fields.leave_year')" hide-label :value="$year" min="2000" max="2100" class="md:w-28" />
        <x-form.select name="branch_id" :label="__('leave.fields.branch')" hide-label class="md:w-40">
            <option value="">{{ __('leave.fields.branch') }}: {{ __('app.common.all') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="department_id" :label="__('leave.fields.department')" hide-label class="md:w-44">
            <option value="">{{ __('leave.fields.department') }}: {{ __('app.common.all') }}</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->code }} · {{ $department->name }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('leave.balances.title')" data-region="leave-balance-list">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('leave.fields.employee') }}</x-table.th>
                @foreach ($types as $type)
                    <x-table.th class="text-end"><span class="inline-flex items-center gap-1.5">@include('admin.leave._swatch', ['color' => $type->color]) {{ $type->name }}</span></x-table.th>
                @endforeach
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($employees as $employee)
            <x-table.row data-href="{{ route('admin.leave-balances.show', [$employee, 'year' => $year]) }}">
                <x-table.td :label="__('leave.fields.employee')">
                    <span class="block text-label-md">{{ $employee->name }}</span>
                    <span class="block text-caption-md text-text-tertiary"><span dir="ltr">{{ $employee->employee_number }}</span> · {{ $employee->department?->name }}</span>
                </x-table.td>
                @foreach ($types as $type)
                    @php $cell = $summary[$employee->id][$type->id]; @endphp
                    <x-table.td :label="$type->name" class="text-end tabular-nums">
                        <span @class(['block text-label-md', 'text-feedback-danger-text' => bccomp($cell['available'], '0', 2) < 0])>{{ $cell['available'] }}</span>
                        <span class="block text-caption-md text-text-tertiary">{{ __('leave.balances.used_pending', ['used' => $cell['used'], 'pending' => $cell['pending']]) }}</span>
                    </x-table.td>
                @endforeach
                <x-table.td>
                    <div class="flex justify-end">
                        <x-icon-button icon="eye" size="sm" :href="route('admin.leave-balances.show', [$employee, 'year' => $year])" :label="__('leave.balances.breakdown')" />
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="$types->count() + 2" icon="users" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$employees" />
        </x-slot:footer>
    </x-table>
@endsection
