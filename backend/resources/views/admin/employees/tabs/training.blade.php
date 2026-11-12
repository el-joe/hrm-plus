{{--
    Training tab (training.view): enrolments, certificates with validity and skills.
    Variables: $employee, $trainingEnrolments, $trainingCertificates, $employeeSkills, $reminderDays.
--}}
<div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
    <x-card :title="__('training.enrolments.title')" padding="none" class="lg:col-span-2">
        <x-slot:actions>
            <x-button size="sm" variant="ghost" :href="route('admin.training-enrolments.index', ['search' => $employee->employee_number])">{{ __('training.common.view_all') }}</x-button>
        </x-slot:actions>
        <x-table :caption="__('training.enrolments.title')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('training.fields.course') }}</x-table.th>
                    <x-table.th>{{ __('training.fields.dates') }}</x-table.th>
                    <x-table.th>{{ __('training.fields.attendance_rate') }}</x-table.th>
                    <x-table.th>{{ __('training.fields.status') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($trainingEnrolments as $enrolment)
                <x-table.row :data-href="route('admin.training-enrolments.show', $enrolment)">
                    <x-table.td :label="__('training.fields.course')">
                        <a class="text-text-link hover:underline" href="{{ route('admin.training-enrolments.show', $enrolment) }}">{{ $enrolment->session->course->name }}</a>
                    </x-table.td>
                    <x-table.td :label="__('training.fields.dates')">@include('admin.training._session-when', ['session' => $enrolment->session])</x-table.td>
                    <x-table.td :label="__('training.fields.attendance_rate')"><span dir="ltr">{{ $enrolment->attendanceRate() }}%</span></x-table.td>
                    <x-table.td :label="__('training.fields.status')"><x-badge :tone="$enrolment->status->tone()" dot>{{ $enrolment->status->label() }}</x-badge></x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="4" icon="book-open" :title="__('training.enrolments.empty')" />
            @endforelse
        </x-table>
    </x-card>

    <x-card :title="__('training.skills.title')">
        <ul class="flex flex-col gap-2">
            @forelse ($employeeSkills as $row)
                <li class="flex items-center justify-between gap-2">
                    <span class="text-body-sm text-text-primary">{{ $row->skill->name }}</span>
                    @include('admin.training._level', ['level' => $row->level])
                </li>
            @empty
                <li class="text-body-sm text-text-tertiary">{{ __('training.skills_matrix.none') }}</li>
            @endforelse
        </ul>
    </x-card>

    <x-card :title="__('training.certificates.title')" padding="none" class="lg:col-span-3">
        <x-slot:actions>
            @can('create', App\Models\TrainingCertificate::class)
                <x-button size="sm" variant="secondary" icon="plus" :href="route('admin.training-certificates.create', ['employee' => $employee->id])">{{ __('training.certificates.create') }}</x-button>
            @endcan
        </x-slot:actions>
        <x-table :caption="__('training.certificates.title')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('training.fields.name') }}</x-table.th>
                    <x-table.th>{{ __('training.fields.issued_on') }}</x-table.th>
                    <x-table.th>{{ __('training.fields.expires_on') }}</x-table.th>
                    <x-table.th>{{ __('training.fields.validity') }}</x-table.th>
                    <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($trainingCertificates as $certificate)
                <x-table.row>
                    <x-table.td :label="__('training.fields.name')">{{ $certificate->name }}<span class="block text-caption-md text-text-tertiary">{{ $certificate->type->label() }}</span></x-table.td>
                    <x-table.td :label="__('training.fields.issued_on')"><span dir="ltr">{{ $certificate->issued_on?->toDateString() ?? '—' }}</span></x-table.td>
                    <x-table.td :label="__('training.fields.expires_on')"><span dir="ltr">{{ $certificate->expires_on?->toDateString() ?? '—' }}</span></x-table.td>
                    <x-table.td :label="__('training.fields.validity')">@include('admin.training._validity')</x-table.td>
                    <x-table.td class="text-end">
                        @if ($certificate->hasFile())
                            <x-icon-button icon="download" size="sm" :href="route('admin.training-certificates.download', $certificate)" :label="__('training.certificates.download')" />
                        @endif
                    </x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="5" icon="award" :title="__('training.certificates.empty')" />
            @endforelse
        </x-table>
    </x-card>
</div>
