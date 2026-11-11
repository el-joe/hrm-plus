{{-- Personal tab. Variables: $employee, $identity, $identityRevealed, $canRevealIdentity, $contacts, $groups, $customFields, $account. --}}
@php($none = __('structure.common.not_set'))
<div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
    <div class="flex flex-col gap-4 lg:col-span-2 lg:gap-6">
        <x-card :title="__('employees.form.identity')">
            <x-detail-list>
                <x-detail-item :label="__('employees.fields.name_ar')"><span lang="ar" dir="rtl">{{ $employee->name_ar }}</span></x-detail-item>
                <x-detail-item :label="__('employees.fields.name_en')"><span lang="en" dir="ltr">{{ $employee->name_en }}</span></x-detail-item>
                <x-detail-item :label="__('employees.fields.employee_number')"><span dir="ltr">{{ $employee->employee_number }}</span></x-detail-item>
                <x-detail-item :label="__('employees.fields.date_of_birth')"><span dir="ltr">{{ $employee->date_of_birth?->toDateString() ?? $none }}</span></x-detail-item>
                <x-detail-item :label="__('employees.fields.gender')">{{ $employee->gender?->label() ?? $none }}</x-detail-item>
                <x-detail-item :label="__('employees.fields.marital_status')">{{ $employee->marital_status?->label() ?? $none }}</x-detail-item>
                <x-detail-item :label="__('employees.fields.nationality')"><span dir="ltr">{{ $employee->nationality ?? $none }}</span></x-detail-item>
                <x-detail-item :label="__('employees.fields.passport_expiry_date')"><span dir="ltr">{{ $employee->passport_expiry_date?->toDateString() ?? $none }}</span></x-detail-item>
            </x-detail-list>
        </x-card>

        <x-card :title="__('employees.profile.identity_numbers')" :subtitle="__('employees.profile.identity_numbers_help')" data-region="identity">
            @if ($canRevealIdentity)
                <x-slot:actions>
                    @if ($identityRevealed)
                        <x-button variant="ghost" size="sm" icon="eye-off" :href="route('admin.employees.show', $employee)">{{ __('employees.profile.hide') }}</x-button>
                    @else
                        <x-button variant="secondary" size="sm" icon="eye" :href="route('admin.employees.show', [$employee, 'reveal' => 1])">{{ __('employees.profile.reveal') }}</x-button>
                    @endif
                </x-slot:actions>
            @endif
            <x-detail-list>
                <x-detail-item :label="__('employees.fields.national_id')"><span dir="ltr" class="tabular-nums" data-sensitive="national_id">{{ $identity['national_id'] ?? $none }}</span></x-detail-item>
                <x-detail-item :label="__('employees.fields.passport_number')"><span dir="ltr" class="tabular-nums" data-sensitive="passport_number">{{ $identity['passport_number'] ?? $none }}</span></x-detail-item>
            </x-detail-list>
            @if ($identityRevealed)
                <p class="mt-3 flex items-center gap-1.5 text-caption-md text-text-tertiary"><x-icon name="lock" size="sm" /> {{ __('employees.profile.access_logged') }}</p>
            @endif
        </x-card>

        <x-card :title="__('employees.form.contact')">
            <x-detail-list>
                <x-detail-item :label="__('employees.fields.email')"><span dir="ltr">{{ $employee->email ?? $none }}</span></x-detail-item>
                <x-detail-item :label="__('employees.fields.personal_email')"><span dir="ltr">{{ $employee->personal_email ?? $none }}</span></x-detail-item>
                <x-detail-item :label="__('employees.fields.phone')"><span dir="ltr">{{ $employee->phone ?? $none }}</span></x-detail-item>
                <x-detail-item :label="__('employees.fields.alternate_phone')"><span dir="ltr">{{ $employee->alternate_phone ?? $none }}</span></x-detail-item>
                <x-detail-item :label="__('employees.fields.address')" class="sm:col-span-2">
                    {{ collect([$employee->address, $employee->city, $employee->country_code])->filter()->implode('، ') ?: $none }}
                </x-detail-item>
            </x-detail-list>
        </x-card>

        <x-card :title="__('employees.form.emergency')">
            @forelse ($contacts as $contact)
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border-subtle py-3 first:pt-0 last:border-0 last:pb-0">
                    <span>
                        <span class="block text-label-md text-text-primary">{{ $contact->name }}</span>
                        <span class="block text-caption-md text-text-tertiary">{{ $contact->relationship }}</span>
                    </span>
                    <span class="text-body-sm text-text-secondary" dir="ltr">{{ $contact->phone }}@if ($contact->alternate_phone) · {{ $contact->alternate_phone }}@endif</span>
                </div>
            @empty
                <p class="text-body-sm text-text-tertiary">{{ __('employees.profile.no_contacts') }}</p>
            @endforelse
        </x-card>

        @if ($customFields !== [])
            <x-card :title="__('employees.form.custom_fields')">
                <x-detail-list>
                    @foreach ($customFields as $row)
                        <x-detail-item :label="$row['field']->name">{{ $row['value'] ?? $none }}</x-detail-item>
                    @endforeach
                </x-detail-list>
            </x-card>
        @endif
    </div>

    <div class="flex flex-col gap-4 lg:gap-6">
        @include('admin.employees.tabs._account')

        <x-card :title="__('employees.fields.groups')">
            <div class="flex flex-wrap gap-2">
                @forelse ($groups as $group)
                    <x-badge tone="primary">{{ $group->name }}</x-badge>
                @empty
                    <p class="text-body-sm text-text-tertiary">{{ __('employees.profile.no_groups') }}</p>
                @endforelse
            </div>
        </x-card>
    </div>
</div>
