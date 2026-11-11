@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('leave.balances.title'), 'url' => route('admin.leave-balances.index', ['year' => $year])]],
    'subtitle' => $employee->employee_number.' · '.$employee->department?->name.' · '.$employee->employmentType?->name,
])

@section('title', __('leave.balances.title_for', ['name' => $employee->name]))

@section('page-actions')
    <div class="flex items-center gap-1">
        <x-icon-button icon="chevron-left" flip size="sm" :href="route('admin.leave-balances.show', [$employee, 'year' => $year - 1])" :label="__('leave.balances.previous_year')" />
        <span class="px-2 text-label-md tabular-nums">{{ $yearLabel }}</span>
        <x-icon-button icon="chevron-right" flip size="sm" :href="route('admin.leave-balances.show', [$employee, 'year' => $year + 1])" :label="__('leave.balances.next_year')" />
    </div>
    @can('createFor', [App\Models\LeaveRequest::class, $employee])
        <x-button icon="plus" :href="route('admin.leave-requests.create', ['employee_id' => $employee->id])">{{ __('leave.requests.create') }}</x-button>
    @endcan
@endsection

@section('content')
    @include('admin.leave._balance-cards')

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('leave.balances.ledger')" :subtitle="__('leave.balances.ledger_help')" padding="none" class="lg:col-span-2" data-region="leave-ledger">
            <x-table :caption="__('leave.balances.ledger')">
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('leave.fields.effective_date') }}</x-table.th>
                        <x-table.th>{{ __('leave.fields.leave_type_id') }}</x-table.th>
                        <x-table.th>{{ __('leave.fields.type') }}</x-table.th>
                        <x-table.th class="text-end">{{ __('leave.fields.amount') }}</x-table.th>
                        <x-table.th>{{ __('leave.fields.reason') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($transactions as $row)
                    <x-table.row>
                        <x-table.td :label="__('leave.fields.effective_date')" class="tabular-nums"><span dir="ltr">{{ $row->effective_date->format('Y-m-d') }}</span></x-table.td>
                        <x-table.td :label="__('leave.fields.leave_type_id')">{{ $row->leaveType->name }}</x-table.td>
                        <x-table.td :label="__('leave.fields.type')">
                            {{ $row->type->label() }}
                            @if ($row->expires_on)
                                <span class="block text-caption-md text-text-tertiary">{{ __('leave.balances.expires_on', ['date' => $row->expires_on->format('Y-m-d')]) }}</span>
                            @endif
                        </x-table.td>
                        <x-table.td :label="__('leave.fields.amount')" @class(['text-end tabular-nums', 'text-feedback-danger-text' => bccomp((string) $row->amount, '0', 2) < 0])><span dir="ltr">{{ bccomp((string) $row->amount, '0', 2) > 0 ? '+' : '' }}{{ $row->amount }}</span></x-table.td>
                        <x-table.td :label="__('leave.fields.reason')" class="text-caption-md text-text-secondary">
                            @if ($row->leave_request_id)
                                <a class="text-text-link hover:underline" href="{{ route('admin.leave-requests.show', $row->leave_request_id) }}">{{ __('leave.balances.request_ref', ['id' => $row->leave_request_id]) }}</a>
                            @endif
                            {{ $row->reason }}
                            @if ($row->creator)
                                <span class="block text-text-tertiary">{{ $row->creator->name }}</span>
                            @endif
                        </x-table.td>
                    </x-table.row>
                @empty
                    <x-table.empty :colspan="5" icon="coins" :title="__('leave.balances.no_ledger')" />
                @endforelse
            </x-table>
        </x-card>

        <div class="flex flex-col gap-4 lg:gap-6">
            @can('leave_balances.update')
                <x-card :title="__('leave.balances.adjust_title')" :subtitle="__('leave.balances.adjust_help')" data-region="leave-adjust">
                    <form method="POST" action="{{ route('admin.leave-balances.transactions.store', $employee) }}" class="flex flex-col gap-3" data-validate>
                        @csrf
                        <input type="hidden" name="leave_year" value="{{ $year }}">
                        <x-form.select name="leave_type_id" id="adjust-type" :label="__('leave.fields.leave_type_id')" required>
                            @foreach ($balanceTypes as $type)
                                <option value="{{ $type->id }}" @selected((string) old('leave_type_id') === (string) $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.select name="type" :label="__('leave.fields.type')" required>
                            @foreach ($manualTypes as $kind)
                                <option value="{{ $kind->value }}" @selected(old('type') === $kind->value)>{{ $kind->label() }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.input type="number" name="amount" step="0.5" min="-366" max="366" :label="__('leave.fields.amount')" :value="old('amount')" required :help="__('leave.help.amount')" />
                        <x-form.input type="date" name="effective_date" :label="__('leave.fields.effective_date')" :value="old('effective_date', now()->toDateString())" required />
                        <x-form.textarea name="reason" id="adjust-reason" :label="__('leave.fields.reason')" required rows="2" maxlength="1000">{{ old('reason') }}</x-form.textarea>
                        <x-button icon="check">{{ __('leave.balances.record') }}</x-button>
                    </form>
                </x-card>
            @endcan

            <x-card :title="__('leave.history.title')" padding="none" data-region="leave-history">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($requests as $leave)
                        <li class="flex flex-col gap-1 px-5 py-3 text-body-sm">
                            <div class="flex items-center justify-between gap-2">
                                <a class="text-text-link hover:underline" href="{{ route('admin.leave-requests.show', $leave) }}">{{ $leave->leaveType->name }}</a>
                                @include('admin.leave._status')
                            </div>
                            <span class="text-caption-md text-text-tertiary">@include('admin.leave._period') · {{ trans_choice('leave.common.days_count', (int) ceil((float) $leave->days), ['count' => $leave->days]) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-body-sm text-text-tertiary">{{ __('leave.history.empty') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
@endsection
