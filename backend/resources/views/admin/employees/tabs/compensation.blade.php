{{--
    Compensation tab (Phase 7a; employee_financials.view, opening it is logged). Variables: $employee, $structure (?StructureSnapshot),
    $structureAssignment, $employeeAssignments, $applying (list<PayEntryInput>), $entries, $loans, $structuresList, $currency, $month.
--}}
@php($user = auth()->user())
<div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
    <x-card :title="__('employees.financial.salary')" data-region="basic">
        <p class="text-numeric-xl text-text-primary" dir="ltr">{{ App\Support\Money::format($employee->basic_salary) }} <span class="text-body-md text-text-tertiary">{{ $currency }}</span></p>
        @if ($employee->pay_basis)
            <p class="mt-1"><x-badge tone="info">{{ $employee->pay_basis->label() }}</x-badge></p>
        @endif
        <p class="mt-4 flex items-center gap-1.5 text-caption-md text-text-tertiary"><x-icon name="lock" size="sm" /> {{ __('employees.profile.access_logged') }}</p>
    </x-card>

    <x-card :title="__('payroll.compensation.structure')" class="lg:col-span-2" data-region="structure">
        @if ($structure)
            <x-slot:actions>
                @can('view', App\Models\SalaryStructure::find($structure->structureId))
                    <x-button variant="ghost" size="sm" :href="route('admin.salary-structures.show', $structure->structureId)">{{ $structure->code }}</x-button>
                @endcan
            </x-slot:actions>
            <p class="text-label-lg text-text-primary">{{ $structure->name }}</p>
            <p class="text-caption-md text-text-tertiary">
                {{ __('payroll.compensation.version', ['version' => $structure->version, 'date' => $structure->versionFrom]) }} ·
                {{ __('payroll.compensation.via', ['scope' => $structure->scope->label()]) }}
            </p>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($structure->lines as $line)
                    <li><x-badge :tone="$line->kind === App\Enums\PayComponentKind::Earning ? 'success' : ($line->kind === App\Enums\PayComponentKind::Deduction ? 'danger' : 'neutral')">{{ $line->name() }}</x-badge></li>
                @endforeach
            </ul>
        @else
            <p class="text-body-sm text-text-tertiary">{{ __('payroll.compensation.no_structure') }}</p>
        @endif

        @if ($user->can('salary_structures.update') && $user->can('updateFinancial', $employee) && $structuresList->isNotEmpty())
            <form method="POST" action="{{ route('admin.employees.salary-structure.store', $employee) }}" class="mt-4 grid gap-3 border-t border-border-subtle pt-4 sm:grid-cols-[2fr_1fr_auto] sm:items-end" data-validate>
                @csrf
                <x-form.select name="salary_structure_id" id="assign-structure" :label="__('payroll.compensation.assign')" required>
                    @foreach ($structuresList as $option)
                        <option value="{{ $option->id }}">{{ $option->code }} · {{ $option->name }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input type="date" name="effective_from" id="assign-from" required :label="__('payroll.fields.effective_from')" :value="now()->startOfMonth()->addMonthNoOverflow()->toDateString()" />
                <x-button variant="secondary" icon="check">{{ __('payroll.structures.assign') }}</x-button>
            </form>
        @endif
    </x-card>
</div>

@if ($employeeAssignments->isNotEmpty())
    <x-card :title="__('payroll.compensation.employee_assignments')" padding="none">
        <x-table :caption="__('payroll.compensation.employee_assignments')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('payroll.structures.title') }}</x-table.th>
                    <x-table.th>{{ __('payroll.fields.effective_from') }}</x-table.th>
                    <x-table.th>{{ __('payroll.fields.reason') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($employeeAssignments as $assignment)
                <x-table.row>
                    <x-table.td :label="__('payroll.structures.title')">{{ $assignment->structure->name }} @if ($structureAssignment?->id === $assignment->id) <x-badge tone="success" dot>{{ __('payroll.common.current') }}</x-badge> @endif</x-table.td>
                    <x-table.td :label="__('payroll.fields.effective_from')" class="whitespace-nowrap"><span dir="ltr">{{ $assignment->effective_from->toDateString() }} → {{ $assignment->effective_to?->toDateString() ?? '…' }}</span></x-table.td>
                    <x-table.td :label="__('payroll.fields.reason')" class="text-body-sm text-text-secondary">{{ $assignment->reason ?? '—' }} <span class="block text-caption-md text-text-tertiary">{{ $assignment->creator?->name }}</span></x-table.td>
                </x-table.row>
            @endforeach
        </x-table>
    </x-card>
@endif

<x-card :title="__('payroll.compensation.applying', ['month' => $month->translatedFormat('F Y')])" :subtitle="__('payroll.compensation.applying_help')" padding="none" data-region="applying">
    <x-slot:actions>
        @can('create', [App\Models\PayEntry::class, App\Enums\PayComponentKind::Earning])
            <x-button size="sm" variant="secondary" icon="plus" :href="route('admin.allowances.create', ['employee' => $employee->id])">{{ __('payroll.compensation.add_allowance') }}</x-button>
        @endcan
        @can('create', [App\Models\PayEntry::class, App\Enums\PayComponentKind::Deduction])
            <x-button size="sm" variant="secondary" icon="minus" :href="route('admin.deductions.create', ['employee' => $employee->id])">{{ __('payroll.compensation.add_deduction') }}</x-button>
        @endcan
    </x-slot:actions>
    <x-table :caption="__('payroll.compensation.applying', ['month' => $month->translatedFormat('F Y')])">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('payroll.fields.reference') }}</x-table.th>
                <x-table.th>{{ __('payroll.fields.salary_component_id') }}</x-table.th>
                <x-table.th>{{ __('payroll.fields.target_type') }}</x-table.th>
                <x-table.th>{{ __('payroll.entries.value') }}</x-table.th>
                <x-table.th>{{ __('payroll.fields.recurrence') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($applying as $input)
            <x-table.row>
                <x-table.td :label="__('payroll.fields.reference')" class="whitespace-nowrap"><span dir="ltr">{{ $input->reference }}</span></x-table.td>
                <x-table.td :label="__('payroll.fields.salary_component_id')">
                    <x-badge :tone="$input->rule->kind === App\Enums\PayComponentKind::Earning ? 'success' : 'danger'">{{ $input->rule->kind->label() }}</x-badge>
                    {{ $input->rule->name() }}
                </x-table.td>
                <x-table.td :label="__('payroll.fields.target_type')">{{ $input->target->label() }}</x-table.td>
                <x-table.td :label="__('payroll.entries.value')">
                    @if ($input->rule->calculationType === App\Enums\PayCalculationType::Percentage)
                        {{ __('payroll.common.percent_of', ['rate' => App\Services\Payroll\PayrollMath::trim($input->rule->rate), 'base' => $input->rule->baseCode ?? __('payroll.common.basic_salary')]) }}
                    @else
                        @include('admin.payroll._money', ['amount' => $input->rule->amount, 'currency' => $currency])
                    @endif
                </x-table.td>
                <x-table.td :label="__('payroll.fields.recurrence')">
                    {{ $input->recurrence->label() }}
                    @if ($input->instalmentNumber) <span class="block text-caption-md text-text-tertiary">{{ $input->instalmentNumber }} / {{ $input->instalmentCount }}</span> @endif
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="coins" :title="__('payroll.compensation.nothing_applies')" />
        @endforelse
    </x-table>
</x-card>

<div class="grid gap-4 lg:grid-cols-2 lg:gap-6">
    <x-card :title="__('payroll.compensation.own_entries')" padding="none" data-region="own-entries">
        <ul class="divide-y divide-border-subtle">
            @forelse ($entries as $entry)
                @php($resource = $entry->kind === App\Enums\PayComponentKind::Deduction ? 'deductions' : 'allowances')
                <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                    <span>
                        @can('view', $entry)
                            <a href="{{ route('admin.'.$resource.'.show', $entry) }}" class="text-label-md text-text-link hover:underline" dir="ltr">{{ $entry->reference }}</a>
                        @else
                            <span dir="ltr">{{ $entry->reference }}</span>
                        @endcan
                        <span class="block text-caption-md text-text-tertiary">{{ $entry->component->name }} · {{ $entry->recurrence->label() }} · <span dir="ltr">{{ $entry->start_date->toDateString() }}</span></span>
                    </span>
                    <span class="flex items-center gap-2">@include('admin.payroll.entries._value') @include('admin.payroll._request-status', ['status' => $entry->status])</span>
                </li>
            @empty
                <li class="px-5 py-6"><x-empty-state icon="coins" :title="__('payroll.entries.empty')" /></li>
            @endforelse
        </ul>
    </x-card>

    <x-card :title="__('payroll.compensation.loans')" padding="none" data-region="loans">
        <x-slot:actions>
            @if ($user->can('createFor', [App\Models\EmployeeLoan::class, $employee]))
                <x-button size="sm" variant="secondary" icon="plus" :href="route('admin.loans.create', ['employee' => $employee->id])">{{ __('payroll.compensation.request_loan') }}</x-button>
            @endif
        </x-slot:actions>
        <ul class="divide-y divide-border-subtle">
            @forelse ($loans as $loan)
                <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                    <span>
                        @can('view', $loan)
                            <a href="{{ route('admin.loans.show', $loan) }}" class="text-label-md text-text-link hover:underline" dir="ltr">{{ $loan->reference }}</a>
                        @else
                            <span dir="ltr">{{ $loan->reference }}</span>
                        @endcan
                        <span class="block text-caption-md text-text-tertiary">{{ $loan->type->label() }} · @include('admin.payroll._money', ['amount' => $loan->approved_amount ?? $loan->requested_amount])</span>
                    </span>
                    <span class="flex items-center gap-2">
                        <span class="text-caption-md text-text-secondary">{{ __('payroll.loans.fields.outstanding') }}: @include('admin.payroll._money', ['amount' => $loan->outstanding_amount])</span>
                        @include('admin.payroll._loan-status')
                    </span>
                </li>
            @empty
                <li class="px-5 py-6"><x-empty-state icon="wallet" :title="__('payroll.loans.empty')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
