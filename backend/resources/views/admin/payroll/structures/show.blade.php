@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('payroll.structures.title'), 'url' => route('admin.salary-structures.index')]],
    'subtitle' => $record->code,
])

@section('title', $record->name)

@section('page-actions')
    @can('update', $record)
        <x-button variant="secondary" icon="pencil" :href="route('admin.salary-structures.edit', $record)">{{ __('app.actions.edit') }}</x-button>
        <x-button icon="plus" :href="route('admin.salary-structures.versions.create', $record)">{{ __('payroll.structures.new_version') }}</x-button>
    @endcan
@endsection

@section('content')
    @if ($errors->any())
        <x-alert tone="danger">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        @include('admin.structure._status-badge', ['active' => $record->is_active])
        @if ($current)
            <x-badge tone="primary">{{ __('payroll.structures.current_version') }}: {{ __('payroll.common.version', ['version' => $current->version]) }}</x-badge>
        @else
            <x-badge tone="warning">{{ __('payroll.structures.no_version') }}</x-badge>
        @endif
        @if ($record->description)
            <p class="w-full text-body-sm text-text-secondary">{{ $record->description }}</p>
        @endif
    </div>

    <section class="flex flex-col gap-4" aria-labelledby="versions-title">
        <h2 id="versions-title" class="text-h4 text-text-primary">{{ __('payroll.structures.versions') }}</h2>
        @foreach ($versions as $version)
            @php($isCurrent = $current?->id === $version->id)
            <x-card padding="none" :title="__('payroll.common.version', ['version' => $version->version])"
                :subtitle="$version->effective_from->toDateString().' → '.($version->effective_to?->toDateString() ?? __('payroll.common.open_ended'))">
                <x-slot:actions>
                    @if ($isCurrent)
                        <x-badge tone="success" dot>{{ __('payroll.common.current') }}</x-badge>
                    @elseif ($version->effective_from->toDateString() > $today)
                        <x-badge tone="info">{{ __('payroll.common.future') }}</x-badge>
                    @else
                        <x-badge>{{ __('payroll.common.ended') }}</x-badge>
                    @endif
                </x-slot:actions>
                <x-table :caption="__('payroll.structures.lines')">
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('payroll.fields.salary_component_id') }}</x-table.th>
                            <x-table.th>{{ __('payroll.fields.kind') }}</x-table.th>
                            <x-table.th>{{ __('payroll.components.rule') }}</x-table.th>
                            <x-table.th>{{ __('payroll.structures.override') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($version->lines as $line)
                        <x-table.row>
                            <x-table.td :label="__('payroll.fields.salary_component_id')">
                                <span class="block text-label-md text-text-primary">{{ $line->component->name }}</span>
                                <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $line->component->code }}</span>
                            </x-table.td>
                            <x-table.td :label="__('payroll.fields.kind')">{{ $line->component->kind->label() }}</x-table.td>
                            <x-table.td :label="__('payroll.components.rule')" class="text-body-sm">@include('admin.payroll.components._rule', ['component' => $line->component])</x-table.td>
                            <x-table.td :label="__('payroll.structures.override')" class="text-body-sm text-text-secondary">
                                @if ($line->amount !== null)
                                    <span dir="ltr">{{ App\Support\Money::format($line->amount) }}</span>
                                @endif
                                @if ($line->rate !== null)
                                    <span class="block" dir="ltr">{{ App\Services\Payroll\PayrollMath::trim($line->rate) }}{{ $line->component->calculation_type === App\Enums\PayCalculationType::Percentage ? '%' : '×' }}</span>
                                @endif
                                @if ($line->baseComponent)
                                    <span class="block">{{ __('payroll.fields.base_component_id') }}: {{ $line->baseComponent->name }}</span>
                                @endif
                                @if ($line->amount === null && $line->rate === null && ! $line->baseComponent)
                                    —
                                @endif
                            </x-table.td>
                        </x-table.row>
                    @endforeach
                </x-table>
                @if ($version->notes || $version->creator)
                    <x-slot:footer>
                        <p class="text-caption-md text-text-tertiary">{{ $version->notes }} @if ($version->creator) · {{ $version->creator->name }} @endif</p>
                    </x-slot:footer>
                @endif
            </x-card>
        @endforeach
    </section>

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('payroll.structures.assignments')" :subtitle="__('payroll.help.assignment')" padding="none" class="lg:col-span-2" data-region="assignments">
            <x-table :caption="__('payroll.structures.assignments')">
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('payroll.fields.scope_type') }}</x-table.th>
                        <x-table.th>{{ __('payroll.fields.effective_from') }}</x-table.th>
                        <x-table.th>{{ __('payroll.fields.reason') }}</x-table.th>
                        <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($assignments as $assignment)
                    @php($inForce = $assignment->effective_from->toDateString() <= $today && ($assignment->effective_to === null || $assignment->effective_to->toDateString() >= $today))
                    <x-table.row>
                        <x-table.td :label="__('payroll.fields.scope_type')">
                            {{ $assignment->scopeLabel() }}
                            @if ($inForce) <x-badge tone="success" dot>{{ __('payroll.common.current') }}</x-badge> @endif
                        </x-table.td>
                        <x-table.td :label="__('payroll.fields.effective_from')" class="whitespace-nowrap tabular-nums">
                            <span dir="ltr">{{ $assignment->effective_from->toDateString() }} → {{ $assignment->effective_to?->toDateString() ?? '…' }}</span>
                        </x-table.td>
                        <x-table.td :label="__('payroll.fields.reason')" class="text-body-sm text-text-secondary">
                            {{ $assignment->reason ?? '—' }}
                            <span class="block text-caption-md text-text-tertiary">{{ $assignment->creator?->name }}</span>
                        </x-table.td>
                        <x-table.td>
                            @can('update', $record)
                                <div class="flex flex-wrap items-end justify-end gap-2">
                                    @if ($assignment->effective_from->toDateString() > $today)
                                        <form method="POST" action="{{ route('admin.salary-structures.assignments.destroy', $assignment) }}" data-confirm="{{ __('payroll.structures.confirm_withdraw') }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-button variant="ghost" size="sm" icon="x">{{ __('payroll.structures.withdraw') }}</x-button>
                                        </form>
                                    @elseif ($assignment->effective_to === null)
                                        <form method="POST" action="{{ route('admin.salary-structures.assignments.end', $assignment) }}" class="flex items-end gap-2" data-validate>
                                            @csrf
                                            @method('PATCH')
                                            <x-form.input type="date" name="effective_to" :id="'end-'.$assignment->id" :label="__('payroll.structures.end_on')" hide-label required :value="$today" class="w-40" />
                                            <x-button variant="ghost" size="sm" icon="power">{{ __('payroll.structures.end') }}</x-button>
                                        </form>
                                    @endif
                                </div>
                            @endcan
                        </x-table.td>
                    </x-table.row>
                @empty
                    <x-table.empty :colspan="4" icon="layers" :title="__('payroll.structures.no_assignments')" />
                @endforelse
            </x-table>
        </x-card>

        @can('update', $record)
            <x-card :title="__('payroll.structures.assign')" data-region="assign-form">
                <form method="POST" action="{{ route('admin.salary-structures.assignments.store', $record) }}" class="flex flex-col gap-3" data-validate>
                    @csrf
                    @include('admin.payroll._target-fields', ['typeName' => 'scope_type', 'selected' => 'job_grade', 'values' => [], 'prefix' => 'assign'])
                    <x-form.input type="date" name="effective_from" required :label="__('payroll.fields.effective_from')" :value="old('effective_from', now()->startOfMonth()->toDateString())" />
                    <x-form.input name="reason" maxlength="1000" :label="__('payroll.fields.reason')" :value="old('reason')" />
                    <x-button icon="check">{{ __('payroll.structures.assign') }}</x-button>
                </form>
            </x-card>
        @endcan
    </div>
@endsection
