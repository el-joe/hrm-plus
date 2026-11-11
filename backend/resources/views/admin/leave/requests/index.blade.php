@extends('layouts.admin', ['subtitle' => __('leave.requests.subtitle')])

@section('title', __('leave.requests.title'))

@section('page-actions')
    @can('create', App\Models\LeaveRequest::class)
        <x-button icon="plus" :href="route('admin.leave-requests.create')">{{ __('leave.requests.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.leave-requests.index')" :reset="route('admin.leave-requests.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="__('leave.common.search_employee')" :value="$filters['search'] ?? ''" class="md:w-52" data-filter-debounce />
        <x-form.select name="status" :label="__('leave.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('leave.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="leave_type_id" :label="__('leave.fields.leave_type_id')" hide-label class="md:w-44">
            <option value="">{{ __('leave.fields.leave_type_id') }}: {{ __('app.common.all') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected((string) ($filters['leave_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
            @endforeach
        </x-form.select>
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
        <x-form.input name="from" type="date" :label="__('leave.fields.from')" hide-label :value="$filters['from'] ?? ''" class="md:w-40" />
        <x-form.input name="to" type="date" :label="__('leave.fields.to')" hide-label :value="$filters['to'] ?? ''" class="md:w-40" />
        <x-form.checkbox name="mine" value="1" :label="__('leave.requests.awaiting_me')" :checked="request()->boolean('mine')" />
    </x-filter-bar>

    <x-table :caption="__('leave.requests.title')" data-region="leave-requests">
        <x-slot:head>
            <tr>
                <x-table.th>#</x-table.th>
                <x-table.th class="min-w-48">{{ __('leave.fields.employee') }}</x-table.th>
                <x-table.th>{{ __('leave.fields.leave_type_id') }}</x-table.th>
                <x-table.th>{{ __('leave.fields.period') }}</x-table.th>
                <x-table.th class="text-end">{{ __('leave.fields.days') }}</x-table.th>
                <x-table.th>{{ __('leave.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($requests as $leave)
            @php $approval = $leave->latestApprovalRequest; @endphp
            <x-table.row>
                <x-table.td label="#" class="tabular-nums"><a href="{{ route('admin.leave-requests.show', $leave) }}" class="text-text-link hover:underline">{{ $leave->id }}</a></x-table.td>
                <x-table.td :label="__('leave.fields.employee')">
                    <span class="block text-label-md">{{ $leave->employee->name }}</span>
                    <span class="block text-caption-md text-text-tertiary"><span dir="ltr">{{ $leave->employee->employee_number }}</span> · {{ $leave->employee->department?->name }}</span>
                </x-table.td>
                <x-table.td :label="__('leave.fields.leave_type_id')">
                    <span class="inline-flex items-center gap-2">@include('admin.leave._swatch', ['color' => $leave->leaveType->color]) {{ $leave->leaveType->name }}</span>
                </x-table.td>
                <x-table.td :label="__('leave.fields.period')" class="tabular-nums">@include('admin.leave._period')</x-table.td>
                <x-table.td :label="__('leave.fields.days')" class="text-end tabular-nums">{{ $leave->days }}</x-table.td>
                <x-table.td :label="__('leave.fields.status')">
                    @include('admin.leave._status')
                    @if ($approval?->isPending() && $approval->currentStep)
                        <span class="mt-1 block text-caption-md text-text-tertiary">{{ __('leave.requests.at_step', ['step' => $approval->currentStep->name]) }}</span>
                    @endif
                </x-table.td>
                <x-table.td>
                    <div class="flex items-center justify-end gap-1">
                        @can('decide', $leave)
                            <form method="POST" action="{{ route('admin.leave-requests.decide', [$leave, 'approve']) }}"
                                data-confirm="{{ __('leave.requests.confirm_approve', ['employee' => $leave->employee->name, 'days' => $leave->days]) }}" data-confirm-tone="info"
                                data-confirm-label="{{ __('leave.common.approve') }}">
                                @csrf
                                <x-icon-button type="submit" icon="check" size="sm" :label="__('leave.common.approve')" />
                            </form>
                            <x-icon-button icon="x" size="sm" variant="danger" :href="route('admin.leave-requests.show', $leave).'#decision'" :label="__('leave.common.reject')"
                                data-leave-reject="{{ route('admin.leave-requests.decide', [$leave, 'reject']) }}" data-subject="{{ $leave->employee->name }} · {{ $leave->leaveType->name }} · {{ $leave->period() }}" />
                        @endcan
                        <x-icon-button icon="eye" size="sm" :href="route('admin.leave-requests.show', $leave)" :label="__('app.actions.view')" />
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="inbox" :title="__('leave.requests.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$requests" />
        </x-slot:footer>
    </x-table>

    <x-modal id="leave-reject-modal" :title="__('leave.requests.reject_title')">
        <form method="POST" action="" id="leave-reject-form" class="flex flex-col gap-3" data-validate>
            @csrf
            <p class="text-body-md text-text-secondary" data-leave-reject-subject></p>
            <x-form.textarea name="reason" id="leave-reject-reason" :label="__('leave.fields.rejection_reason')" required rows="3" maxlength="1000" />
        </form>
        <x-slot:footer>
            <x-button type="button" variant="secondary" data-modal-close>{{ __('app.actions.cancel') }}</x-button>
            <x-button type="submit" variant="danger" form="leave-reject-form" icon="x">{{ __('leave.common.reject') }}</x-button>
        </x-slot:footer>
    </x-modal>
@endsection
