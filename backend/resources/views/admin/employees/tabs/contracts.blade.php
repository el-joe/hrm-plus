{{-- Contracts tab. Variables: $employee, $contracts. --}}
<x-card :title="__('employees.tabs.contracts')" padding="none">
    @can('update', $employee)
        <x-slot:actions>
            <x-button size="sm" icon="plus" :href="route('admin.employees.contracts.create', $employee)">{{ __('employees.contracts.add') }}</x-button>
        </x-slot:actions>
    @endcan
    <x-table :caption="__('employees.tabs.contracts')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('employees.fields.contract_type') }}</x-table.th>
                <x-table.th>{{ __('employees.fields.start_date') }}</x-table.th>
                <x-table.th>{{ __('employees.fields.end_date') }}</x-table.th>
                <x-table.th>{{ __('employees.fields.status') }}</x-table.th>
                <x-table.th>{{ __('employees.contracts.file') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($contracts as $contract)
            <x-table.row>
                <x-table.td :label="__('employees.fields.contract_type')">
                    <span class="block text-label-md text-text-primary">{{ $contract->contract_type->label() }}</span>
                    <span class="block text-caption-md text-text-tertiary">
                        @if ($contract->reference)<span dir="ltr">{{ $contract->reference }}</span>@endif
                        @if ($contract->renewalOf) · {{ __('employees.contracts.renewal_of', ['date' => $contract->renewalOf->start_date->toDateString()]) }}@endif
                    </span>
                </x-table.td>
                <x-table.td :label="__('employees.fields.start_date')" class="tabular-nums"><span dir="ltr">{{ $contract->start_date->toDateString() }}</span></x-table.td>
                <x-table.td :label="__('employees.fields.end_date')" class="tabular-nums">
                    <span dir="ltr">{{ $contract->end_date?->toDateString() ?? __('employees.contracts.open_ended') }}</span>
                    @if ($contract->terminated_on)
                        <span class="block text-caption-md text-feedback-danger-text">{{ __('employees.contracts.terminated_on', ['date' => $contract->terminated_on->toDateString()]) }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('employees.fields.status')">
                    <x-badge :tone="['active' => 'success', 'upcoming' => 'info', 'expired' => 'neutral', 'terminated' => 'danger'][$contract->state()]" dot>{{ __('employees.contracts.states.'.$contract->state()) }}</x-badge>
                </x-table.td>
                <x-table.td :label="__('employees.contracts.file')">
                    @if ($contract->file_path)
                        <a href="{{ route('admin.employees.contracts.download', [$employee, $contract]) }}" class="inline-flex items-center gap-1 text-text-link hover:underline">
                            <x-icon name="download" size="sm" /> <span class="max-w-40 truncate">{{ $contract->file_name }}</span>
                        </a>
                    @else
                        <span class="text-text-tertiary">—</span>
                    @endif
                </x-table.td>
                <x-table.td>
                    @can('update', $employee)
                        <div class="flex items-center justify-end gap-1">
                            @if ($contract->end_date && ! $contract->terminated_on && ! $contract->renewedBy()->exists())
                                <x-button variant="ghost" size="sm" :href="route('admin.employees.contracts.renew-form', [$employee, $contract])">{{ __('employees.contracts.renew') }}</x-button>
                            @endif
                            <x-icon-button icon="pencil" size="sm" :href="route('admin.employees.contracts.edit', [$employee, $contract])" :label="__('app.actions.edit')" />
                        </div>
                    @endcan
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="file-text" :title="__('employees.contracts.empty')" />
        @endforelse
    </x-table>
</x-card>
