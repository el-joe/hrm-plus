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
        @if ($pendingAcknowledgements)
            <x-alert tone="warning" data-notice="pending-acknowledgements">
                {{ __('announcements.portal.pending_acknowledgements', ['count' => $pendingAcknowledgements]) }}
                <a class="ms-1 text-text-link hover:underline" href="{{ route('portal.announcements.index', ['filter' => 'unread']) }}">{{ __('notifications.open') }}</a>
            </x-alert>
        @endif

        <div class="grid gap-4 md:gap-6 lg:grid-cols-2">
        <x-card :title="__('app.dashboard.my_profile')" class="min-w-0">
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

        <x-card :title="__('leave.portal.home_title')" class="min-w-0" data-region="home-leave">
            @include('admin.leave._balance-cards', ['balances' => $leaveBalances, 'compact' => true])
            @if ($pendingLeave)
                <p class="mt-3 text-body-sm text-text-secondary">{{ trans_choice('leave.portal.pending_count', $pendingLeave, ['count' => $pendingLeave]) }}</p>
            @endif
            <x-slot:footer>
                <x-button variant="secondary" size="sm" :href="route('portal.leaves')" icon="calendar">{{ __('leave.portal.open') }}</x-button>
            </x-slot:footer>
        </x-card>

        {{-- Phase 8b: pending requests, latest announcements, notifications (spec section 5, Employee Home). --}}
        <x-card :title="__('hr_requests.portal.home_title')" class="min-w-0" data-region="home-requests">
            <p class="text-body-sm text-text-secondary">{{ trans_choice('hr_requests.portal.open_count', $openRequests, ['count' => $openRequests]) }}</p>
            <ul class="mt-3 divide-y divide-border-subtle">
                @forelse ($recentRequests as $hrRequest)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                        <a href="{{ route('portal.requests.show', $hrRequest->id) }}" class="min-w-0 flex-1 truncate text-label-md text-text-link hover:underline">{{ $hrRequest->subject }}</a>
                        <x-badge :tone="$hrRequest->status->tone()">{{ $hrRequest->status->label() }}</x-badge>
                    </li>
                @empty
                    <li class="py-2.5 text-body-sm text-text-tertiary">{{ __('hr_requests.portal.empty') }}</li>
                @endforelse
            </ul>
            <x-slot:footer>
                <div class="flex flex-wrap gap-2">
                    <x-button size="sm" :href="route('portal.requests.create')" icon="plus">{{ __('hr_requests.portal.new') }}</x-button>
                    <x-button variant="secondary" size="sm" :href="route('portal.requests.index')" icon="inbox">{{ __('hr_requests.portal.open_requests') }}</x-button>
                </div>
            </x-slot:footer>
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
                    {{ __('app.nav.announcements') }}@if ($unreadAnnouncements) ({{ $unreadAnnouncements }})@endif
                </x-button>
            </x-slot:footer>
        </x-card>
        </div>
    @else
        <x-alert tone="warning" data-notice="no-employee-profile">{{ __('app.dashboard.no_employee_profile') }}</x-alert>
    @endif

    <x-card :title="__('notifications.title')" data-region="home-notifications">
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
                {{ __('notifications.view_all') }}@if ($unreadNotifications) ({{ $unreadNotifications }})@endif
            </x-button>
        </x-slot:footer>
    </x-card>
@endsection
