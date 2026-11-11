{{-- Leave tab (Phase 6): balances of the current leave year and the latest requests. --}}
<x-card :title="__('leave.balances.title_year', ['year' => $leaveYear])">
    @include('admin.leave._balance-cards', ['balances' => $balances, 'compact' => true])
    <x-slot:footer>
        <div class="flex flex-wrap gap-2">
            @can('leave_balances.view')
                <x-button variant="secondary" size="sm" icon="coins" :href="route('admin.leave-balances.show', $employee)">{{ __('leave.balances.breakdown') }}</x-button>
            @endcan
            @can('createFor', [App\Models\LeaveRequest::class, $employee])
                <x-button variant="secondary" size="sm" icon="plus" :href="route('admin.leave-requests.create', ['employee_id' => $employee->id])">{{ __('leave.requests.create') }}</x-button>
            @endcan
        </div>
    </x-slot:footer>
</x-card>

<x-card :title="__('leave.history.title')" padding="none" data-region="employee-leave-history">
    <x-table :caption="__('leave.history.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('leave.fields.leave_type_id') }}</x-table.th>
                <x-table.th>{{ __('leave.fields.period') }}</x-table.th>
                <x-table.th class="text-end">{{ __('leave.fields.days') }}</x-table.th>
                <x-table.th>{{ __('leave.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($leaveRequests as $leave)
            <x-table.row>
                <x-table.td :label="__('leave.fields.leave_type_id')">
                    @can('leaves.view')
                        <a class="text-text-link hover:underline" href="{{ route('admin.leave-requests.show', $leave) }}">{{ $leave->leaveType->name }}</a>
                    @else
                        {{ $leave->leaveType->name }}
                    @endcan
                </x-table.td>
                <x-table.td :label="__('leave.fields.period')" class="tabular-nums">@include('admin.leave._period')</x-table.td>
                <x-table.td :label="__('leave.fields.days')" class="text-end tabular-nums">{{ $leave->days }}</x-table.td>
                <x-table.td :label="__('leave.fields.status')">@include('admin.leave._status')</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="4" icon="calendar" :title="__('leave.history.empty')" />
        @endforelse
    </x-table>
</x-card>
