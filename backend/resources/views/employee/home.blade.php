@extends('layouts.employee', ['hero' => true])

@section('title', __('app.dashboard.welcome', ['name' => $user->name]))

@php
    $digits = fn ($text): string => \App\Support\Formatter::digits($text);
    $taskRoutes = [
        'onboarding_task' => fn (array $task): string => route('portal.onboarding'),
        'acknowledge_asset' => fn (array $task): string => route('portal.assets'),
        'self_assessment' => fn (array $task): string => route('portal.performance.reviews.show', $task['id']),
        'acknowledge_review' => fn (array $task): string => route('portal.performance.reviews.show', $task['id']),
    ];
@endphp

@section('hero')
    <section class="bg-surface-brand text-text-on-primary">
        <div class="mx-auto flex max-w-312 flex-col gap-2 px-4 py-6 md:px-8 md:py-10 2xl:px-0">
            <h1 class="text-h3 md:text-h1">{{ __('app.dashboard.welcome', ['name' => $user->name]) }}</h1>
            <p class="text-body-md text-text-on-brand-muted md:text-body-lg">
                <time datetime="{{ now()->toDateString() }}">{{ $digits(now()->locale(app()->getLocale())->isoFormat(__('app.ui.date_long'))) }}</time>
                @if ($employee)
                    · {{ $employee->jobTitle?->name }}
                @endif
            </p>
        </div>
    </section>
@endsection

@section('content')
    @if ($user->isAdmin())
        <x-alert tone="info" data-notice="admin-in-portal">{{ __('app.dashboard.signed_in_as_admin') }}</x-alert>
    @endif

    @if ($employee)
        @if ($pending['announcements_to_acknowledge'])
            <x-alert tone="warning" data-notice="pending-acknowledgements">
                {{ $digits(__('announcements.portal.pending_acknowledgements', ['count' => $pending['announcements_to_acknowledge']])) }}
                <a class="ms-1 text-text-link hover:underline" href="{{ route('portal.announcements.index', ['filter' => 'unread']) }}">{{ __('notifications.open') }}</a>
            </x-alert>
        @endif

        {{-- Today's attendance and the tasks waiting for the employee. --}}
        <div class="grid gap-4 md:gap-6 lg:grid-cols-3">
            <x-card :title="__('app.home.today_title')" class="min-w-0 lg:col-span-2" data-region="home-attendance">
                @php
                    $stateTone = ['checked_in' => 'success', 'checked_out' => 'neutral', 'not_checked_in' => 'warning'][$today['state']] ?? 'neutral';
                @endphp
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex min-w-0 flex-col gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :tone="$stateTone" dot data-attendance-state="{{ $today['state'] }}">{{ __('app.home.states.'.$today['state']) }}</x-badge>
                            @if ($today['status_label'])
                                <x-badge tone="neutral">{{ $today['status_label'] }}</x-badge>
                            @endif
                        </div>
                        @if (! $today['employed'])
                            <p class="text-body-md text-text-secondary">{{ __('app.home.not_employed') }}</p>
                        @elseif ($today['holiday'])
                            <p class="text-body-md text-text-secondary">{{ __('app.home.holiday_today', ['name' => $today['holiday']]) }}</p>
                        @elseif ($today['shifts'] === [])
                            <p class="text-body-md text-text-secondary">{{ __('app.home.rest_day') }}</p>
                        @else
                            @foreach ($today['shifts'] as $shift)
                                <p class="text-body-md text-text-primary">
                                    <x-icon name="clock" size="sm" class="me-1 inline text-text-tertiary" />{{ $shift['name'] }}
                                    <span dir="ltr" class="tabular-nums">{{ $digits($shift['start_local'].' – '.$shift['end_local']) }}</span>
                                </p>
                            @endforeach
                        @endif
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-body-sm sm:flex sm:flex-wrap">
                            <div class="flex gap-1"><dt class="text-text-tertiary">{{ __('app.home.check_in') }}:</dt><dd dir="ltr" class="tabular-nums text-text-primary">{{ $today['check_in_local'] ? $digits($today['check_in_local']) : '—' }}</dd></div>
                            <div class="flex gap-1"><dt class="text-text-tertiary">{{ __('app.home.check_out') }}:</dt><dd dir="ltr" class="tabular-nums text-text-primary">{{ $today['check_out_local'] ? $digits($today['check_out_local']) : '—' }}</dd></div>
                        </dl>
                    </div>
                </div>
                @if ($today['employed'] && $today['shifts'] !== [] && $today['state'] !== 'checked_out')
                    <p class="mt-4 flex items-start gap-2 rounded-md bg-surface-muted px-3 py-2 text-body-sm text-text-secondary" data-checkin-hint>
                        <x-icon name="smartphone" size="sm" class="mt-0.5 shrink-0 text-text-tertiary" />
                        <span>{{ $today['mobile_checkin_enabled'] ? __('app.home.hint_mobile_'.$today['next_action']) : __('app.home.hint_device') }}</span>
                    </p>
                @endif
                @if ($today['pending_reviews'])
                    <p class="mt-2 text-body-sm text-text-secondary">{{ $digits(trans_choice('app.home.pending_reviews', $today['pending_reviews'], ['count' => $today['pending_reviews']])) }}</p>
                @endif
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" :href="route('portal.attendance')" icon="clock">{{ __('app.nav.my_attendance') }}</x-button>
                </x-slot:footer>
            </x-card>

            <x-card :title="__('app.home.tasks_title')" class="min-w-0" data-region="home-tasks">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($tasks as $task)
                        <li class="flex flex-col gap-0.5 py-2.5 first:pt-0">
                            <a href="{{ $taskRoutes[$task['kind']]($task) }}" class="text-label-md text-text-link hover:underline" dir="auto">{{ $task['title'] ?: __('app.home.tasks.'.$task['kind']) }}</a>
                            <p class="text-caption-md text-text-tertiary">
                                {{ __('app.home.tasks.'.$task['kind']) }}
                                @if ($task['due_date']) · {{ __('app.home.due') }} <x-date :value="$task['due_date']" />@endif
                            </p>
                        </li>
                    @empty
                        <li><x-empty-state icon="check-circle" :title="__('app.home.no_tasks')" class="py-6" /></li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        {{-- This month's attendance. --}}
        <section aria-labelledby="home-month-title" data-region="home-month">
            <h2 id="home-month-title" class="mb-3 text-h5 text-text-primary">
                {{ __('app.home.month_title') }} · <span dir="ltr">{{ $digits(\Carbon\CarbonImmutable::parse($month['month'].'-01')->locale(app()->getLocale())->isoFormat('MMMM YYYY')) }}</span>
            </h2>
            <div class="grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-4">
                <x-stat-card :label="__('app.home.present_days')" :value="\App\Support\Formatter::number($month['present_days'] + $month['late_days'])" icon="check-circle" :href="route('portal.attendance')" />
                <x-stat-card :label="__('app.home.late_days')" :value="\App\Support\Formatter::number($month['late_days'])" icon="clock" :href="route('portal.attendance')" />
                <x-stat-card :label="__('app.home.absent_days')" :value="\App\Support\Formatter::number($month['absent_days'])" icon="alert-triangle" :href="route('portal.attendance')"
                    :hint="$month['missing_punches'] ? trans_choice('app.home.missing_punches', $month['missing_punches'], ['count' => \App\Support\Formatter::number($month['missing_punches'])]) : null" />
                <x-stat-card :label="__('app.home.worked_hours')" :value="\App\Support\Duration::format($month['worked_minutes'], '0:00')" icon="briefcase" :href="route('portal.attendance')"
                    :hint="$month['overtime_minutes'] ? __('app.home.overtime', ['time' => \App\Support\Formatter::digits(\App\Support\Duration::format($month['overtime_minutes']))]) : null" />
            </div>
        </section>

        <div class="grid gap-4 md:gap-6 lg:grid-cols-2">
            <x-card :title="__('leave.portal.home_title')" class="min-w-0" data-region="home-leave">
                @include('admin.leave._balance-cards', ['balances' => $leaveBalances, 'compact' => true])
                @if ($pending['leave_requests'])
                    <p class="mt-3 text-body-sm text-text-secondary">{{ $digits(trans_choice('leave.portal.pending_count', $pending['leave_requests'], ['count' => $pending['leave_requests']])) }}</p>
                @endif
                <x-slot:footer>
                    <div class="flex flex-wrap gap-2">
                        <x-button size="sm" :href="route('portal.leaves').'#new-leave'" icon="plus">{{ __('app.home.request_leave') }}</x-button>
                        <x-button variant="secondary" size="sm" :href="route('portal.leaves')" icon="calendar">{{ __('leave.portal.open') }}</x-button>
                    </div>
                </x-slot:footer>
            </x-card>

            <x-card :title="__('app.home.payslip_title')" class="min-w-0" data-region="home-payslip">
                @if ($latestPayslip)
                    <div class="flex flex-col gap-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-label-md text-text-primary">{{ $latestPayslip->period->name }}</p>
                            <x-badge :tone="$latestPayslip->payment_status->value === 'paid' ? 'success' : 'warning'">{{ $latestPayslip->payment_status->label() }}</x-badge>
                        </div>
                        <p class="text-numeric-xl text-text-primary">
                            <x-number :value="$latestPayslip->net_pay" money /> <span class="text-body-md text-text-tertiary">{{ $latestPayslip->currency }}</span>
                        </p>
                        <p class="text-body-sm text-text-tertiary">{{ __('app.home.net_pay') }} · {{ __('app.home.pay_date') }} <x-date :value="$latestPayslip->period->pay_date" /></p>
                    </div>
                @else
                    <x-empty-state icon="coins" :title="__('app.home.no_payslip')" class="py-6" />
                @endif
                <x-slot:footer>
                    <div class="flex flex-wrap gap-2">
                        @if ($latestPayslip)
                            <x-button size="sm" :href="route('portal.payroll.show', $latestPayslip->id)" icon="file-text">{{ __('app.home.open_payslip') }}</x-button>
                        @endif
                        <x-button variant="secondary" size="sm" :href="route('portal.payroll')" icon="coins">{{ __('app.nav.my_payroll') }}</x-button>
                    </div>
                </x-slot:footer>
            </x-card>

            {{-- Pending requests (spec: Employee Home). --}}
            <x-card :title="__('app.home.pending_title')" class="min-w-0" data-region="home-requests">
                @php
                    $pendingRows = [
                        ['key' => 'leave_requests', 'route' => route('portal.leaves'), 'icon' => 'calendar'],
                        ['key' => 'hr_requests', 'route' => route('portal.requests.index'), 'icon' => 'inbox'],
                        ['key' => 'loan_requests', 'route' => route('portal.loans'), 'icon' => 'wallet'],
                        ['key' => 'attendance_corrections', 'route' => route('portal.attendance'), 'icon' => 'clock'],
                    ];
                @endphp
                <ul class="grid grid-cols-2 gap-2">
                    @foreach ($pendingRows as $row)
                        <li>
                            <a href="{{ $row['route'] }}" class="flex min-h-12 items-center justify-between gap-2 rounded-md border border-border-subtle px-3 py-2 hover:bg-action-neutral-hover" data-pending="{{ $row['key'] }}">
                                <span class="flex min-w-0 items-center gap-2 text-body-sm text-text-secondary">
                                    <x-icon :name="$row['icon']" size="sm" class="shrink-0 text-text-tertiary" />
                                    <span class="truncate">{{ __('app.home.pending.'.$row['key']) }}</span>
                                </span>
                                <x-number :value="$pending[$row['key']]" class="text-label-lg text-text-primary" />
                            </a>
                        </li>
                    @endforeach
                </ul>
                <ul class="mt-3 divide-y divide-border-subtle">
                    @foreach ($recentRequests as $hrRequest)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                            <a href="{{ route('portal.requests.show', $hrRequest->id) }}" class="min-w-0 flex-1 truncate text-label-md text-text-link hover:underline">{{ $hrRequest->subject }}</a>
                            <x-badge :tone="$hrRequest->status->tone()">{{ $hrRequest->status->label() }}</x-badge>
                        </li>
                    @endforeach
                </ul>
                <x-slot:footer>
                    <div class="flex flex-wrap gap-2">
                        <x-button size="sm" :href="route('portal.requests.create')" icon="plus">{{ __('hr_requests.portal.new') }}</x-button>
                        <x-button variant="secondary" size="sm" :href="route('portal.requests.index')" icon="inbox">{{ __('hr_requests.portal.open_requests') }}</x-button>
                    </div>
                </x-slot:footer>
            </x-card>

            <x-card :title="__('app.home.upcoming_title')" class="min-w-0" data-region="home-upcoming">
                <h3 class="mb-2 text-label-md text-text-secondary">{{ __('app.home.next_shifts') }}</h3>
                <ul class="divide-y divide-border-subtle">
                    @forelse (array_slice($upcoming['shifts'], 0, 5) as $shift)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span class="text-body-sm text-text-primary">{{ $digits(\Carbon\CarbonImmutable::parse($shift['date'])->locale(app()->getLocale())->isoFormat('dddd D MMM')) }}</span>
                            <span class="text-body-sm text-text-secondary">{{ $shift['name'] }} · <span dir="ltr" class="tabular-nums">{{ $digits($shift['start_local'].' – '.$shift['end_local']) }}</span></span>
                        </li>
                    @empty
                        <li class="py-2 text-body-sm text-text-tertiary">{{ __('app.home.no_shifts') }}</li>
                    @endforelse
                </ul>
                <h3 class="mb-2 mt-4 text-label-md text-text-secondary">{{ __('app.home.holidays') }}</h3>
                <ul class="divide-y divide-border-subtle">
                    @forelse ($upcoming['holidays'] as $holiday)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span class="text-body-sm text-text-primary">{{ $holiday['name'] }}</span>
                            <x-date :value="$holiday['date']" class="text-body-sm text-text-secondary" />
                        </li>
                    @empty
                        <li class="py-2 text-body-sm text-text-tertiary">{{ __('app.home.no_holidays') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :title="__('announcements.portal.home_title')" class="min-w-0" data-region="home-announcements">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($announcements as $announcement)
                        @php($read = App\Services\Announcements\AnnouncementFeed::readOf($announcement))
                        <li class="flex flex-col gap-1 py-2.5 first:pt-0">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($announcement->is_pinned)<x-icon name="pin" size="sm" class="text-text-tertiary" />@endif
                                <a href="{{ route('portal.announcements.show', $announcement->id) }}" class="min-w-0 flex-1 truncate text-label-md text-text-link hover:underline">{{ $announcement->title() }}</a>
                                @unless ($read)<x-badge tone="primary" dot>{{ __('announcements.portal.new') }}</x-badge>@endunless
                            </div>
                            <p class="line-clamp-2 text-body-sm text-text-secondary">{{ $announcement->summary() }}</p>
                        </li>
                    @empty
                        <li class="py-2.5 text-body-sm text-text-tertiary">{{ __('announcements.portal.empty') }}</li>
                    @endforelse
                </ul>
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" :href="route('portal.announcements.index')" icon="megaphone">
                        {{ __('app.nav.announcements') }}@if ($unreadAnnouncements) (<x-number :value="$unreadAnnouncements" />)@endif
                    </x-button>
                </x-slot:footer>
            </x-card>

            <x-card :title="__('notifications.title')" class="min-w-0" data-region="home-notifications">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($notifications as $item)
                        <li class="flex gap-3 py-2.5 first:pt-0">
                            <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-action-primary-default' => ! $item['read'], 'bg-border-default' => $item['read']]) aria-hidden="true"></span>
                            <div class="min-w-0">
                                <a href="{{ route('portal.notifications.open', $item['id']) }}" class="block truncate text-label-md text-text-primary hover:text-text-link hover:underline" dir="auto">{{ $item['title'] }}</a>
                                <p class="line-clamp-2 text-body-sm text-text-secondary" dir="auto">{{ $item['message'] }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="py-2.5 text-body-sm text-text-tertiary">{{ __('notifications.empty') }}</li>
                    @endforelse
                </ul>
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" :href="route('portal.notifications.index')" icon="bell">
                        {{ __('notifications.view_all') }}@if ($unreadNotifications) (<x-number :value="$unreadNotifications" />)@endif
                    </x-button>
                </x-slot:footer>
            </x-card>

            <x-card :title="__('app.dashboard.my_profile')" class="min-w-0 lg:col-span-2" data-region="home-profile">
                <div class="flex flex-col gap-4 md:flex-row md:items-center">
                    <div class="flex min-w-0 items-center gap-4 md:w-80 md:shrink-0">
                        <x-avatar :name="$employee->name" :src="$employee->photo_path ? route('portal.profile.photo') : null" size="lg" />
                        <div class="min-w-0">
                            <p class="truncate text-h4">{{ $employee->name }}</p>
                            <p class="truncate text-body-sm text-text-tertiary">{{ $employee->jobTitle?->name }} · {{ $employee->department?->name }}</p>
                        </div>
                    </div>
                    <x-detail-list class="min-w-0 flex-1">
                        <x-detail-item :label="__('employees.fields.employee_number')"><span dir="ltr">{{ $employee->employee_number }}</span></x-detail-item>
                        <x-detail-item :label="__('employees.fields.branch_id')">{{ $employee->branch?->name }}</x-detail-item>
                        <x-detail-item :label="__('employees.fields.manager_id')">{{ $employee->manager?->name ?? __('structure.common.not_set') }}</x-detail-item>
                        <x-detail-item :label="__('app.common.email')"><span dir="ltr" class="break-all">{{ $user->email }}</span></x-detail-item>
                    </x-detail-list>
                </div>
                <x-slot:footer>
                    <div class="flex flex-wrap gap-2">
                        <x-button variant="secondary" size="sm" :href="route('portal.profile')" icon="user">{{ __('employees.portal.open_profile') }}</x-button>
                        <x-button variant="secondary" size="sm" :href="route('portal.documents')" icon="file-text">{{ __('app.nav.my_documents') }}</x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </div>
    @else
        <x-alert tone="warning" data-notice="no-employee-profile">{{ __('app.dashboard.no_employee_profile') }}</x-alert>

        <x-card :title="__('notifications.title')" data-region="home-notifications">
            <ul class="divide-y divide-border-subtle">
                @forelse ($notifications as $item)
                    <li class="py-2.5 first:pt-0">
                        <a href="{{ route('portal.notifications.open', $item['id']) }}" class="block truncate text-label-md text-text-primary hover:text-text-link hover:underline" dir="auto">{{ $item['title'] }}</a>
                    </li>
                @empty
                    <li class="py-2.5 text-body-sm text-text-tertiary">{{ __('notifications.empty') }}</li>
                @endforelse
            </ul>
        </x-card>
    @endif
@endsection
