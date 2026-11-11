{{-- Lines of one kind on the breakdown. Variables: $lines (Collection<PayrollItemLine>), $title, $total, $currency. --}}
<x-card :title="$title" padding="none">
    <x-table :caption="$title">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-44">{{ __('payroll_runs.items.line') }}</x-table.th>
                <x-table.th>{{ __('payroll_runs.items.source') }}</x-table.th>
                <x-table.th class="min-w-64">{{ __('payroll_runs.items.explanation') }}</x-table.th>
                <x-table.th class="text-end">{{ __('payroll_runs.runs.amount') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($lines as $line)
            <x-table.row>
                <x-table.td :label="__('payroll_runs.items.line')">
                    <span class="text-label-md text-text-primary">{{ $line->name() }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $line->code }}</span>
                    @if (! $line->show_on_payslip) <x-badge tone="neutral">{{ __('payroll_runs.items.hidden_on_payslip') }}</x-badge> @endif
                </x-table.td>
                <x-table.td :label="__('payroll_runs.items.source')">
                    <x-badge tone="info">{{ $line->source->label() }}</x-badge>
                    @if ($line->is_taxable) <span class="block text-caption-md text-text-tertiary">{{ __('payroll_runs.items.taxable') }}</span> @endif
                    @if ($line->is_insurable) <span class="block text-caption-md text-text-tertiary">{{ __('payroll_runs.items.insurable') }}</span> @endif
                </x-table.td>
                <x-table.td :label="__('payroll_runs.items.explanation')" class="text-body-sm text-text-secondary">
                    @foreach ($line->explanationLines() as $sentence)
                        <span class="block">{{ $sentence }}</span>
                    @endforeach
                </x-table.td>
                <x-table.td :label="__('payroll_runs.runs.amount')" class="text-end">@include('admin.payroll._money', ['amount' => $line->amount, 'currency' => null])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="4" icon="coins" :title="__('payroll_runs.items.no_lines')" />
        @endforelse
        @if ($lines->isNotEmpty())
            <x-slot:footer>
                <div class="flex items-center justify-between px-5 py-3 text-label-md text-text-primary">
                    <span>{{ __('payroll_runs.items.total') }}</span>
                    @include('admin.payroll._money', ['amount' => $total, 'currency' => $currency])
                </div>
            </x-slot:footer>
        @endif
    </x-table>
</x-card>
