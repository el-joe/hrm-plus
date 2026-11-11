@extends('layouts.employee')

@section('title', __('employees.portal.title'))

@section('content')
    @php($none = __('structure.common.not_set'))
    <x-card data-region="summary">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <x-avatar :name="$employee->name" size="xl" />
            <div class="min-w-0">
                <p class="text-h4 text-text-primary">{{ $employee->name }}</p>
                <p class="text-body-md text-text-secondary">{{ $employee->jobTitle?->name }} · {{ $employee->department?->name }}</p>
                <p class="text-caption-md text-text-tertiary"><span dir="ltr">{{ $employee->employee_number }}</span> · {{ $employee->status->label() }}</p>
            </div>
        </div>
    </x-card>

    <x-tabs :label="__('employees.profile.sections')" :items="collect(App\Http\Controllers\Employee\ProfileController::TABS)->map(fn ($item) => [
        'label' => __('employees.portal.tabs.'.$item),
        'url' => route('portal.profile', $item === 'personal' ? [] : ['tab' => $item]),
        'active' => $item === $tab,
    ])->all()" />

    @switch($tab)
        @case('personal')
            <div class="grid gap-4 lg:grid-cols-2 lg:gap-6">
                <x-card :title="__('employees.form.identity')">
                    <x-detail-list>
                        <x-detail-item :label="__('employees.fields.name_ar')"><span lang="ar" dir="rtl">{{ $employee->name_ar }}</span></x-detail-item>
                        <x-detail-item :label="__('employees.fields.name_en')"><span lang="en" dir="ltr">{{ $employee->name_en }}</span></x-detail-item>
                        <x-detail-item :label="__('employees.fields.date_of_birth')"><span dir="ltr">{{ $employee->date_of_birth?->toDateString() ?? $none }}</span></x-detail-item>
                        <x-detail-item :label="__('employees.fields.gender')">{{ $employee->gender?->label() ?? $none }}</x-detail-item>
                        <x-detail-item :label="__('employees.fields.marital_status')">{{ $employee->marital_status?->label() ?? $none }}</x-detail-item>
                        <x-detail-item :label="__('employees.fields.nationality')"><span dir="ltr">{{ $employee->nationality ?? $none }}</span></x-detail-item>
                        <x-detail-item :label="__('employees.fields.national_id')"><span dir="ltr">{{ $identity['national_id'] ?? $none }}</span></x-detail-item>
                        <x-detail-item :label="__('employees.fields.passport_number')"><span dir="ltr">{{ $identity['passport_number'] ?? $none }}</span></x-detail-item>
                    </x-detail-list>
                </x-card>
                <x-card :title="__('employees.form.contact')">
                    <x-detail-list>
                        <x-detail-item :label="__('employees.fields.email')"><span dir="ltr">{{ $employee->email ?? $none }}</span></x-detail-item>
                        <x-detail-item :label="__('employees.fields.phone')"><span dir="ltr">{{ $employee->phone ?? $none }}</span></x-detail-item>
                        <x-detail-item :label="__('employees.fields.personal_email')"><span dir="ltr">{{ $employee->personal_email ?? $none }}</span></x-detail-item>
                        <x-detail-item :label="__('employees.fields.alternate_phone')"><span dir="ltr">{{ $employee->alternate_phone ?? $none }}</span></x-detail-item>
                        <x-detail-item :label="__('employees.fields.address')" class="sm:col-span-2">{{ collect([$employee->address, $employee->city])->filter()->implode('، ') ?: $none }}</x-detail-item>
                    </x-detail-list>
                </x-card>
                <x-card :title="__('employees.form.emergency')">
                    @forelse ($employee->emergencyContacts as $contact)
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border-subtle py-3 first:pt-0 last:border-0 last:pb-0">
                            <span><span class="block text-label-md">{{ $contact->name }}</span><span class="block text-caption-md text-text-tertiary">{{ $contact->relationship }}</span></span>
                            <span class="text-body-sm text-text-secondary" dir="ltr">{{ $contact->phone }}</span>
                        </div>
                    @empty
                        <p class="text-body-sm text-text-tertiary">{{ __('employees.profile.no_contacts') }}</p>
                    @endforelse
                </x-card>
                @if ($customFields !== [])
                    <x-card :title="__('employees.portal.more')">
                        <x-detail-list>
                            @foreach ($customFields as $row)
                                <x-detail-item :label="$row['field']->name">{{ $row['value'] }}</x-detail-item>
                            @endforeach
                        </x-detail-list>
                    </x-card>
                @endif
            </div>
            <x-alert tone="info">{{ __('employees.portal.change_notice') }}</x-alert>
            @break

        @case('employment')
            <x-card :title="__('employees.form.employment')">
                <x-detail-list>
                    <x-detail-item :label="__('employees.fields.branch_id')">{{ $employee->branch?->name }}</x-detail-item>
                    <x-detail-item :label="__('employees.fields.department_id')">{{ $employee->department?->name }}</x-detail-item>
                    <x-detail-item :label="__('employees.fields.team_id')">{{ $employee->team?->name ?? $none }}</x-detail-item>
                    <x-detail-item :label="__('employees.fields.job_title_id')">{{ $employee->jobTitle?->name ?? $none }}</x-detail-item>
                    <x-detail-item :label="__('employees.fields.job_grade_id')">{{ $employee->jobGrade?->name ?? $none }}</x-detail-item>
                    <x-detail-item :label="__('employees.fields.employment_type_id')">{{ $employee->employmentType?->name }}</x-detail-item>
                    <x-detail-item :label="__('employees.fields.manager_id')">
                        {{ $employee->manager?->name ?? $none }}
                        @if ($employee->manager?->email) <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $employee->manager->email }}</span> @endif
                    </x-detail-item>
                    <x-detail-item :label="__('employees.fields.work_location_id')">{{ $employee->workLocation?->name ?? $none }}</x-detail-item>
                    <x-detail-item :label="__('employees.fields.hire_date')"><span dir="ltr">{{ $employee->hire_date?->toDateString() }}</span></x-detail-item>
                    <x-detail-item :label="__('employees.fields.probation_end_date')"><span dir="ltr">{{ $employee->probation_end_date?->toDateString() ?? $none }}</span></x-detail-item>
                    <x-detail-item :label="__('employees.profile.contract_period')">
                        @if ($contract)
                            {{ $contract->contract_type->label() }} · <span dir="ltr">{{ $contract->start_date->toDateString() }} → {{ $contract->end_date?->toDateString() ?? __('employees.contracts.open_ended') }}</span>
                        @else
                            {{ $none }}
                        @endif
                    </x-detail-item>
                </x-detail-list>
            </x-card>
            @break

        @case('documents')
            <x-card :title="__('employees.portal.tabs.documents')" padding="none">
                <x-table :caption="__('employees.portal.tabs.documents')">
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('employees.fields.title') }}</x-table.th>
                            <x-table.th>{{ __('employees.fields.category') }}</x-table.th>
                            <x-table.th>{{ __('employees.fields.expiry_date') }}</x-table.th>
                            <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($documents as $document)
                        <x-table.row>
                            <x-table.td :label="__('employees.fields.title')" class="text-label-md">{{ $document->title }}</x-table.td>
                            <x-table.td :label="__('employees.fields.category')">{{ $document->category->label() }}</x-table.td>
                            <x-table.td :label="__('employees.fields.expiry_date')">
                                <span dir="ltr">{{ $document->expiry_date?->toDateString() ?? '—' }}</span>
                                @if ($document->isExpired()) <x-badge tone="danger">{{ __('employees.documents.expired') }}</x-badge> @endif
                            </x-table.td>
                            <x-table.td class="text-end">
                                <x-button variant="ghost" size="sm" icon="download" :href="route('portal.documents.download', $document->id)">{{ __('employees.documents.download') }}</x-button>
                            </x-table.td>
                        </x-table.row>
                    @empty
                        <x-table.empty :colspan="4" icon="file-text" :title="__('employees.portal.no_documents')" />
                    @endforelse
                </x-table>
            </x-card>
            @break

        @case('history')
            <x-card :title="__('employees.portal.tabs.history')">
                <ol class="flex flex-col gap-4 border-s border-border-default ps-5">
                    @forelse ($history as $row)
                        <li>
                            <p class="text-caption-md text-text-tertiary" dir="ltr">{{ $row->effective_date->toDateString() }}</p>
                            <p class="text-label-md text-text-primary">{{ $row->change_type->label() }}</p>
                            <p class="text-body-sm text-text-secondary">{{ $row->jobTitle?->name }} · {{ $row->department?->name }} · {{ $row->branch?->name }}</p>
                        </li>
                    @empty
                        <li class="text-body-sm text-text-tertiary">{{ __('employees.history.empty') }}</li>
                    @endforelse
                </ol>
            </x-card>
            @break
    @endswitch
@endsection
