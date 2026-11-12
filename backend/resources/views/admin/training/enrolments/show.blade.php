@extends('layouts.admin', [
    'breadcrumbs' => [
        ['label' => __('training.enrolments.title'), 'url' => route('admin.training-enrolments.index')],
        ['label' => $enrolment->session->reference, 'url' => route('admin.training-sessions.show', $enrolment->session)],
    ],
    'subtitle' => $enrolment->session->course->name,
])

@section('title', $enrolment->employee->label())

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

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-card :title="__('training.enrolments.details')">
                <x-slot:actions><x-badge :tone="$enrolment->status->tone()" dot>{{ $enrolment->status->label() }}</x-badge></x-slot:actions>
                <x-detail-list>
                    <x-detail-item :label="__('training.fields.employee')">
                        @can('view', $enrolment->employee)
                            <a class="text-text-link hover:underline" href="{{ route('admin.employees.tab', [$enrolment->employee, 'training']) }}">{{ $enrolment->employee->label() }}</a>
                        @else
                            {{ $enrolment->employee->label() }}
                        @endcan
                    </x-detail-item>
                    <x-detail-item :label="__('training.fields.department')">{{ $enrolment->employee->department?->name ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('training.fields.session')">
                        <a class="text-text-link hover:underline" href="{{ route('admin.training-sessions.show', $enrolment->session) }}">{{ $enrolment->session->course->name }} · <span dir="ltr">{{ $enrolment->session->reference }}</span></a>
                    </x-detail-item>
                    <x-detail-item :label="__('training.fields.dates')">@include('admin.training._session-when', ['session' => $enrolment->session])</x-detail-item>
                    <x-detail-item :label="__('training.fields.source')">{{ $enrolment->source->label() }} · {{ $enrolment->requester?->name ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('training.fields.cost')">@include('admin.payroll._money', ['amount' => $enrolment->cost, 'currency' => $currency])</x-detail-item>
                    @if ($enrolment->reason)
                        <x-detail-item :label="__('training.fields.reason')">{{ $enrolment->reason }}</x-detail-item>
                    @endif
                    @if ($enrolment->rejection_reason)
                        <x-detail-item :label="__('training.common.reject_reason')">{{ $enrolment->rejection_reason }}</x-detail-item>
                    @endif
                    <x-detail-item :label="__('training.fields.attendance_rate')"><span dir="ltr">{{ $attendanceRate }}%</span></x-detail-item>
                    @if ($enrolment->score !== null)
                        <x-detail-item :label="__('training.fields.score')">{{ $enrolment->score }}</x-detail-item>
                    @endif
                    @if ($enrolment->completion_note)
                        <x-detail-item :label="__('training.fields.note')">{{ $enrolment->completion_note }}</x-detail-item>
                    @endif
                    @if ($enrolment->certificate)
                        <x-detail-item :label="__('training.certificates.one')">
                            {{ $enrolment->certificate->name }} @include('admin.training._validity', ['certificate' => $enrolment->certificate])
                        </x-detail-item>
                    @endif
                </x-detail-list>
            </x-card>

            <x-card :title="__('training.sessions.attendance')" padding="none">
                <x-table :caption="__('training.sessions.attendance')">
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('training.fields.date') }}</x-table.th>
                            <x-table.th>{{ __('training.fields.attendance') }}</x-table.th>
                            <x-table.th>{{ __('training.fields.note') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($days as $date)
                        @php($row = $enrolment->attendances->first(fn ($attendance) => $attendance->date->toDateString() === $date))
                        <x-table.row>
                            <x-table.td :label="__('training.fields.date')"><span dir="ltr">{{ $date }}</span></x-table.td>
                            <x-table.td :label="__('training.fields.attendance')">
                                @if ($row)
                                    <x-badge :tone="$row->status->tone()">{{ $row->status->label() }}</x-badge>
                                @else
                                    <span class="text-text-tertiary">{{ __('training.sessions.not_recorded') }}</span>
                                @endif
                            </x-table.td>
                            <x-table.td :label="__('training.fields.note')">{{ $row?->note ?? '—' }}</x-table.td>
                        </x-table.row>
                    @endforeach
                </x-table>
            </x-card>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            @if ($canDecide)
                <x-card :title="__('training.common.decide')" data-region="decision">
                    <p class="mb-3 text-caption-md text-text-tertiary">{{ __('training.sessions.seats_of', ['taken' => $enrolment->session->seatsTaken(), 'capacity' => $enrolment->session->capacity]) }}</p>
                    <form method="POST" action="{{ route('admin.training-enrolments.decide', [$enrolment, 'approve']) }}" class="flex flex-col gap-3">
                        @csrf
                        <x-form.input name="reason" id="approve-reason" :label="__('training.common.comment')" maxlength="1000" />
                        <x-button icon="check">{{ __('training.common.approve') }}</x-button>
                    </form>
                    <form method="POST" action="{{ route('admin.training-enrolments.decide', [$enrolment, 'reject']) }}" class="mt-4 flex flex-col gap-2" data-validate>
                        @csrf
                        <x-form.textarea name="reason" id="reject-reason" :label="__('training.common.reject_reason')" required maxlength="1000" rows="2" />
                        <x-button variant="danger" icon="x-circle">{{ __('training.common.reject') }}</x-button>
                    </form>
                </x-card>
            @endif

            @if ($canUpdate)
                <x-card :title="__('training.sessions.outcomes')" :subtitle="__('training.sessions.outcomes_help', ['percent' => $minAttendance])" data-region="outcome">
                    <form method="POST" action="{{ route('admin.training-enrolments.outcome', $enrolment) }}" class="flex flex-col gap-3" data-validate>
                        @csrf
                        <x-form.select name="status" id="outcome-status" :label="__('training.fields.outcome')" required>
                            @foreach ($outcomes as $outcome)
                                <option value="{{ $outcome->value }}" @selected(old('status', $enrolment->status->value) === $outcome->value)>{{ $outcome->label() }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.input type="number" name="score" step="0.01" min="0" max="100" dir="ltr" :label="__('training.fields.score')" :value="old('score', $enrolment->score)" />
                        <x-form.input name="note" id="outcome-note" :label="__('training.fields.note')" maxlength="1000" :value="old('note', $enrolment->completion_note)" />
                        <x-button variant="secondary" icon="check-circle">{{ __('training.sessions.save_outcomes') }}</x-button>
                    </form>
                </x-card>
            @endif

            @if ($canCancel)
                <x-card :title="__('training.enrolments.cancel')">
                    <form method="POST" action="{{ route('admin.training-enrolments.cancel', $enrolment) }}" class="flex flex-col gap-2" data-validate data-confirm="{{ __('training.enrolments.cancel_confirm') }}" data-confirm-tone="danger">
                        @csrf
                        <x-form.input name="reason" id="cancel-reason" required maxlength="1000" :label="__('training.fields.reason')" />
                        <x-button variant="danger" icon="x">{{ __('training.enrolments.cancel') }}</x-button>
                    </form>
                </x-card>
            @endif

            <x-card :title="__('training.common.approval_history')">
                @include('admin.payroll._approval-history', ['requests' => $enrolment->approvalRequests])
            </x-card>
        </div>
    </div>
@endsection
