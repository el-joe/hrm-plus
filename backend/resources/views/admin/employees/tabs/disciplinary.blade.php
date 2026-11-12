{{--
    Disciplinary tab (restricted, disciplinary.view; opening it is logged): the employee's cases and active warnings.
    Variables: $employee, $disciplinaryCases, $activeWarnings.
--}}
<x-alert tone="warning">{{ __('disciplinary.restricted') }}</x-alert>

<div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
    <x-card :title="__('disciplinary.tab.title')" padding="none" class="lg:col-span-2" data-region="disciplinary-cases">
        <x-slot:actions>
            @can('create', App\Models\DisciplinaryCase::class)
                <x-button size="sm" variant="secondary" icon="plus" :href="route('admin.disciplinary-cases.create', ['employee' => $employee->id])">{{ __('disciplinary.tab.report') }}</x-button>
            @endcan
        </x-slot:actions>
        <x-table :caption="__('disciplinary.tab.title')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('disciplinary.fields.reference') }}</x-table.th>
                    <x-table.th>{{ __('disciplinary.fields.violation_type') }}</x-table.th>
                    <x-table.th>{{ __('disciplinary.fields.incident_date') }}</x-table.th>
                    <x-table.th>{{ __('disciplinary.fields.status') }}</x-table.th>
                    <x-table.th>{{ __('disciplinary.cases.outcome') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($disciplinaryCases as $case)
                <x-table.row :data-href="route('admin.disciplinary-cases.show', $case)">
                    <x-table.td :label="__('disciplinary.fields.reference')"><a class="text-label-md text-text-link hover:underline" dir="ltr" href="{{ route('admin.disciplinary-cases.show', $case) }}">{{ $case->reference }}</a></x-table.td>
                    <x-table.td :label="__('disciplinary.fields.violation_type')">{{ $case->violationType->name }}</x-table.td>
                    <x-table.td :label="__('disciplinary.fields.incident_date')"><span dir="ltr">{{ $case->incident_date->toDateString() }}</span></x-table.td>
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
                <x-table.empty :colspan="5" icon="alert-triangle" :title="__('disciplinary.tab.empty')" />
            @endforelse
        </x-table>
    </x-card>

    <x-card :title="__('disciplinary.actions.active_warnings')" data-region="active-warnings">
        <ul class="flex flex-col gap-2">
            @forelse ($activeWarnings as $warning)
                <li class="flex flex-wrap items-center justify-between gap-2 text-body-sm">
                    <x-badge :tone="$warning->type->tone()">{{ $warning->type->label() }}</x-badge>
                    <span class="text-caption-md text-text-tertiary">{{ __('disciplinary.actions.warning_until', ['date' => $warning->expires_on->toDateString()]) }}</span>
                </li>
            @empty
                <li class="text-body-sm text-text-tertiary">{{ __('disciplinary.actions.no_active_warnings') }}</li>
            @endforelse
        </ul>
    </x-card>
</div>
