@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('training.sessions.title'), 'url' => route('admin.training-sessions.index')]],
    'subtitle' => $session->reference.' · '.$session->course->provider?->name,
])

@section('title', $session->course->name)

@section('page-actions')
    @if ($canManage && $session->isScheduled())
        <x-button variant="secondary" icon="pencil" :href="route('admin.training-sessions.edit', $session)">{{ __('app.actions.edit') }}</x-button>
        @if ($session->start_date->lte(today()))
            <form method="POST" action="{{ route('admin.training-sessions.complete', $session) }}" data-confirm="{{ __('training.sessions.complete_confirm') }}">
                @csrf
                <x-button variant="secondary" icon="check-circle">{{ __('training.sessions.complete') }}</x-button>
            </form>
        @endif
    @endif
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

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('training.fields.seats')" :value="__('training.sessions.seats_of', ['taken' => $seatsTaken, 'capacity' => $session->capacity])" icon="users" />
        <x-stat-card :label="__('training.fields.cost_per_participant')" :value="App\Support\Money::format($session->participantCost())" icon="coins" :hint="$currency" />
        <x-stat-card :label="__('training.fields.dates')" :value="trans_choice('training.sessions.days_count', count($days), ['count' => count($days)])" icon="calendar" :hint="$session->start_date->toDateString()" />
        <x-stat-card :label="__('training.enrolments.awaiting')" :value="$enrolments->where('status', App\Enums\TrainingEnrolmentStatus::Pending)->count()" icon="inbox" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-card :title="__('training.sessions.participants')" padding="none" data-region="participants">
                <x-table :caption="__('training.sessions.participants')">
                    <x-slot:head>
                        <tr>
                            <x-table.th class="min-w-48">{{ __('training.fields.employee') }}</x-table.th>
                            <x-table.th>{{ __('training.fields.source') }}</x-table.th>
                            <x-table.th>{{ __('training.fields.attendance_rate') }}</x-table.th>
                            <x-table.th>{{ __('training.fields.score') }}</x-table.th>
                            <x-table.th>{{ __('training.fields.status') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($enrolments as $enrolment)
                        <x-table.row :data-href="route('admin.training-enrolments.show', $enrolment)">
                            <x-table.td :label="__('training.fields.employee')">
                                <a class="text-label-md text-text-link hover:underline" href="{{ route('admin.training-enrolments.show', $enrolment) }}">{{ $enrolment->employee->label() }}</a>
                                <span class="block text-caption-md text-text-tertiary">{{ $enrolment->employee->department?->name }}</span>
                            </x-table.td>
                            <x-table.td :label="__('training.fields.source')">{{ $enrolment->source->label() }}</x-table.td>
                            <x-table.td :label="__('training.fields.attendance_rate')" class="tabular-nums"><span dir="ltr">{{ $enrolment->attendanceRate(count($days)) }}%</span></x-table.td>
                            <x-table.td :label="__('training.fields.score')" class="tabular-nums">{{ $enrolment->score ?? '—' }}</x-table.td>
                            <x-table.td :label="__('training.fields.status')">
                                <x-badge :tone="$enrolment->status->tone()" dot>{{ $enrolment->status->label() }}</x-badge>
                                @if ($enrolment->isPending() && $enrolment->latestApprovalRequest?->currentStep)
                                    <span class="block text-caption-md text-text-tertiary">{{ __('training.common.at_step', ['step' => $enrolment->latestApprovalRequest->currentStep->name]) }}</span>
                                @endif
                            </x-table.td>
                        </x-table.row>
                    @empty
                        <x-table.empty :colspan="5" icon="users" :title="__('training.sessions.no_participants')" />
                    @endforelse
                </x-table>
            </x-card>

            @if ($canRecord && $seated->isNotEmpty())
                <x-card :title="__('training.sessions.attendance')" :subtitle="__('training.sessions.attendance_help')" data-region="attendance">
                    <x-tabs :items="collect($days)->map(fn ($date) => ['label' => $date, 'url' => route('admin.training-sessions.show', [$session, 'day' => $date]).'#attendance', 'active' => $date === $day])->all()" :label="__('training.sessions.attendance')" class="mb-4" dir="ltr" />
                    @if ($day > now()->toDateString())
                        <p class="text-body-sm text-text-tertiary">{{ __('training.sessions.future_day') }}</p>
                    @else
                        <form method="POST" action="{{ route('admin.training-sessions.attendance', $session) }}" id="attendance" class="flex flex-col gap-3">
                            @csrf
                            <input type="hidden" name="date" value="{{ $day }}">
                            <ul class="divide-y divide-border-subtle">
                                @foreach ($seated as $enrolment)
                                    @php($current = $enrolment->attendances->first(fn ($row) => $row->date->toDateString() === $day))
                                    <li class="grid gap-2 py-2 sm:grid-cols-[1fr_10rem_1fr] sm:items-center">
                                        <span class="text-label-md text-text-primary">{{ $enrolment->employee->label() }}</span>
                                        <x-form.select :name="'attendance['.$enrolment->id.'][status]'" :id="'att-'.$enrolment->id" :label="__('training.fields.attendance')" hide-label>
                                            <option value="">—</option>
                                            @foreach ($attendanceStatuses as $status)
                                                <option value="{{ $status->value }}" @selected(old('attendance.'.$enrolment->id.'.status', $current?->status->value) === $status->value)>{{ $status->label() }}</option>
                                            @endforeach
                                        </x-form.select>
                                        <x-form.input :name="'attendance['.$enrolment->id.'][note]'" :id="'att-note-'.$enrolment->id" :label="__('training.fields.note')" hide-label :placeholder="__('training.fields.note')" maxlength="500" :value="old('attendance.'.$enrolment->id.'.note', $current?->note)" />
                                    </li>
                                @endforeach
                            </ul>
                            <div><x-button icon="check">{{ __('training.sessions.save_attendance') }}</x-button></div>
                        </form>
                    @endif
                </x-card>

                <x-card :title="__('training.sessions.outcomes')" :subtitle="__('training.sessions.outcomes_help', ['percent' => $minAttendance])" data-region="outcomes">
                    <form method="POST" action="{{ route('admin.training-sessions.outcomes', $session) }}" class="flex flex-col gap-3">
                        @csrf
                        <ul class="divide-y divide-border-subtle">
                            @foreach ($seated as $enrolment)
                                <li class="grid gap-2 py-2 sm:grid-cols-[1fr_10rem_7rem] sm:items-center">
                                    <span class="text-label-md text-text-primary">{{ $enrolment->employee->label() }}
                                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $enrolment->attendanceRate(count($days)) }}%</span></span>
                                    <x-form.select :name="'outcomes['.$enrolment->id.'][status]'" :id="'out-'.$enrolment->id" :label="__('training.fields.outcome')" hide-label>
                                        <option value="">{{ $enrolment->isEnrolled() ? '—' : $enrolment->status->label() }}</option>
                                        @foreach ($outcomes as $outcome)
                                            <option value="{{ $outcome->value }}">{{ $outcome->label() }}</option>
                                        @endforeach
                                    </x-form.select>
                                    <x-form.input type="number" :name="'outcomes['.$enrolment->id.'][score]'" :id="'score-'.$enrolment->id" step="0.01" min="0" max="100" dir="ltr" :label="__('training.fields.score')" hide-label :placeholder="__('training.fields.score')" :value="$enrolment->score" />
                                </li>
                            @endforeach
                        </ul>
                        <div><x-button variant="secondary" icon="check-circle">{{ __('training.sessions.save_outcomes') }}</x-button></div>
                    </form>
                </x-card>
            @endif
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('training.sessions.details')">
                <x-slot:actions><x-badge :tone="$session->status->tone()" dot>{{ $session->status->label() }}</x-badge></x-slot:actions>
                <x-detail-list>
                    <x-detail-item :label="__('training.fields.dates')">@include('admin.training._session-when')</x-detail-item>
                    <x-detail-item :label="__('training.fields.delivery_mode')">{{ $session->delivery_mode->label() }}</x-detail-item>
                    @if ($session->location)
                        <x-detail-item :label="__('training.fields.location')">{{ $session->location }}</x-detail-item>
                    @endif
                    @if ($session->meeting_url)
                        <x-detail-item :label="__('training.fields.meeting_url')"><a class="break-all text-text-link hover:underline" href="{{ $session->meeting_url }}" rel="noopener noreferrer" target="_blank" dir="ltr">{{ $session->meeting_url }}</a></x-detail-item>
                    @endif
                    <x-detail-item :label="__('training.fields.trainer')">{{ $session->trainer ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('training.fields.branch')">{{ $session->branch?->name ?? __('training.sessions.all_branches') }}</x-detail-item>
                    <x-detail-item :label="__('training.fields.duration_hours')"><span dir="ltr">{{ $session->course->duration_hours }}</span></x-detail-item>
                    <x-detail-item :label="__('training.courses.skills')">
                        @forelse ($session->course->courseSkills as $row)
                            <span class="block">{{ $row->skill->name }} · {{ $row->level }}/5</span>
                        @empty
                            —
                        @endforelse
                    </x-detail-item>
                    @if ($session->course->issues_certificate)
                        <x-detail-item :label="__('training.fields.issues_certificate')">{{ $session->course->certificate_validity_months ? __('training.courses.certificate_months', ['months' => $session->course->certificate_validity_months]) : __('training.courses.certificate_no_expiry') }}</x-detail-item>
                    @endif
                    @if ($session->notes)
                        <x-detail-item :label="__('training.fields.notes')">{{ $session->notes }}</x-detail-item>
                    @endif
                </x-detail-list>
            </x-card>

            @if ($canNominate)
                <x-card :title="__('training.sessions.nominate')" :subtitle="auth()->user()->can('training.manage') ? __('training.sessions.nominate_help_hr') : __('training.sessions.nominate_help_manager')" data-region="nominate">
                    @if ($seatsTaken >= $session->capacity)
                        <p class="text-body-sm text-text-tertiary">{{ __('training.validation.session_full') }}</p>
                    @else
                        <form method="POST" action="{{ route('admin.training-sessions.nominate', $session) }}" class="flex flex-col gap-3" data-validate>
                            @csrf
                            <x-form.select name="employee_ids[]" id="employee_ids" :label="__('training.fields.employees')" multiple required class="h-48 pe-3" :error-keys="['employee_ids.*']" :help="__('training.sessions.multi_help')">
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected(in_array((string) $employee->id, (array) old('employee_ids', []), true))>{{ $employee->label() }}</option>
                                @endforeach
                            </x-form.select>
                            <x-form.input name="reason" :label="__('training.fields.reason')" maxlength="1000" :value="old('reason')" />
                            <x-button icon="user-plus">{{ __('training.sessions.nominate') }}</x-button>
                        </form>
                    @endif
                </x-card>
            @endif

            @if ($canManage && $session->isScheduled())
                <x-card :title="__('training.sessions.cancel')" :subtitle="__('training.sessions.cancel_help')" data-region="cancel-session">
                    <form method="POST" action="{{ route('admin.training-sessions.cancel', $session) }}" class="flex flex-col gap-3" data-validate
                        data-confirm="{{ __('training.sessions.cancel_confirm') }}" data-confirm-tone="danger">
                        @csrf
                        <x-form.textarea name="reason" id="cancel-reason" :label="__('training.fields.reason')" required maxlength="1000" rows="2" />
                        <x-button variant="danger" icon="x">{{ __('training.sessions.cancel') }}</x-button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
@endsection
