@extends('layouts.employee', ['hero' => true])

@section('title', __('app.dashboard.welcome', ['name' => $user->name]))

@section('hero')
    <section class="bg-surface-brand text-text-on-primary">
        <div class="mx-auto flex max-w-312 flex-col gap-2 px-4 py-8 md:px-8 md:py-10 2xl:px-0">
            <h1 class="text-h2 md:text-h1">{{ __('app.dashboard.welcome', ['name' => $user->name]) }}</h1>
            <p class="text-body-lg text-text-on-brand-muted">
                <time datetime="{{ now()->toDateString() }}">{{ now()->locale(app()->getLocale())->isoFormat(__('app.ui.date_long')) }}</time>
            </p>
        </div>
    </section>
@endsection

@section('content')
    @if ($user->isAdmin())
        <x-alert tone="info" data-notice="admin-in-portal">{{ __('app.dashboard.signed_in_as_admin') }}</x-alert>
    @endif

    @if ($employee)
        <x-card :title="__('app.dashboard.my_profile')" class="max-w-2xl">
            <div class="mb-5 flex items-center gap-4">
                <x-avatar :name="$employee->name" size="lg" />
                <div class="min-w-0">
                    <p class="truncate text-h4">{{ $employee->name }}</p>
                    <p class="truncate text-body-sm text-text-tertiary">{{ $employee->jobTitle?->name }} · {{ $employee->department?->name }}</p>
                </div>
            </div>
            <x-detail-list>
                <x-detail-item :label="__('employees.fields.employee_number')"><span dir="ltr">{{ $employee->employee_number }}</span></x-detail-item>
                <x-detail-item :label="__('employees.fields.branch_id')">{{ $employee->branch?->name }}</x-detail-item>
                <x-detail-item :label="__('employees.fields.manager_id')">{{ $employee->manager?->name ?? __('structure.common.not_set') }}</x-detail-item>
                <x-detail-item :label="__('app.common.email')"><span dir="ltr">{{ $user->email }}</span></x-detail-item>
            </x-detail-list>
            <x-slot:footer>
                <x-button variant="secondary" size="sm" :href="route('portal.profile')" icon="user">{{ __('employees.portal.open_profile') }}</x-button>
            </x-slot:footer>
        </x-card>
    @else
        <x-alert tone="warning" data-notice="no-employee-profile">{{ __('app.dashboard.no_employee_profile') }}</x-alert>
    @endif
@endsection
