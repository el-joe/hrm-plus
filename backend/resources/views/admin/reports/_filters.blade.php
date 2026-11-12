{{--
    Shared filter bar of the reports centre: the shared filters the report declares
    (App\Services\Reports\ReportFilters) plus its own selects / dates. GET form; works without JS.
--}}
@php
    $shared = $report->filters();
    $select = fn (string $filter) => in_array($filter, $shared, true) && ! empty($options[$filter] ?? []);
    $param = \App\Services\Reports\ReportFilters::PARAMS;
@endphp

<x-filter-bar :action="route('admin.reports.show', $report->key())" :reset="route('admin.reports.show', $report->key())" data-report-filters>
    @if (in_array('date_range', $shared, true))
        <x-form.input name="from" type="date" :label="__('reports.filters.from')" :value="$filters['from'] ?? ''" class="md:w-40" />
        <x-form.input name="to" type="date" :label="__('reports.filters.to')" :value="$filters['to'] ?? ''" class="md:w-40" />
    @endif

    @if (in_array('employee', $shared, true))
        <x-form.input name="employee" type="search" icon="search" :label="__('reports.filters.employee')" :placeholder="__('reports.filters.employee_placeholder')" :value="$filters['employee'] ?? ''" class="md:w-52" data-filter-debounce />
    @endif

    @foreach (['branch', 'department', 'team', 'position', 'job_title', 'employee_group', 'employment_type', 'employee_status', 'manager'] as $filter)
        @if ($select($filter))
            <x-form.select :name="$param[$filter]" :label="__('reports.filters.'.$filter)" class="md:w-44">
                <option value="">{{ __('app.common.all') }}</option>
                @foreach ($options[$filter] as $value => $text)
                    <option value="{{ $value }}" @selected((string) ($filters[$param[$filter]] ?? '') === (string) $value)>{{ $text }}</option>
                @endforeach
            </x-form.select>
        @endif
    @endforeach

    @foreach ($report->extraFilters() as $key => $extra)
        @if (($extra['type'] ?? 'select') === 'date')
            <x-form.input :name="$key" type="date" :label="$extra['label']" :value="$filters[$key] ?? ''" class="md:w-40" />
        @else
            <x-form.select :name="$key" :label="$extra['label']" class="md:w-44">
                @unless (array_key_exists($key, $report->defaults()))
                    <option value="">{{ __('app.common.all') }}</option>
                @endunless
                @foreach ($extra['options'] as $value => $text)
                    <option value="{{ $value }}" @selected((string) ($filters[$key] ?? '') === (string) $value)>{{ $text }}</option>
                @endforeach
            </x-form.select>
        @endif
    @endforeach
</x-filter-bar>
