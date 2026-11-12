@extends('layouts.employee', ['subtitle' => __('announcements.portal.subtitle')])

@section('title', __('announcements.portal.title'))

@section('content')
    @if ($pendingAcknowledgements > 0)
        <x-alert tone="warning" data-notice="pending-acknowledgements">{{ __('announcements.portal.pending_acknowledgements', ['count' => $pendingAcknowledgements]) }}</x-alert>
    @endif

    <x-tabs :label="__('announcements.portal.title')" :items="[
        ['label' => __('announcements.portal.filter_all'), 'url' => route('portal.announcements.index'), 'active' => ! $unreadOnly],
        ['label' => __('announcements.portal.filter_unread', ['count' => $unreadCount]), 'url' => route('portal.announcements.index', ['filter' => 'unread']), 'active' => $unreadOnly],
    ]" />

    <x-card padding="none" data-region="my-announcements">
        <ul class="divide-y divide-border-subtle">
            @forelse ($announcements as $announcement)
                @php($read = App\Services\Announcements\AnnouncementFeed::readOf($announcement))
                <li @class(['flex flex-col gap-2 px-5 py-4', 'bg-action-primary-subtle/40' => $read === null]) data-announcement-id="{{ $announcement->id }}">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($announcement->is_pinned)
                            <x-icon name="pin" size="sm" class="text-text-link" /><span class="sr-only">{{ __('announcements.portal.pinned') }}</span>
                        @endif
                        <a href="{{ route('portal.announcements.show', $announcement->id) }}" @class(['min-w-0 flex-1 text-text-link hover:underline', 'text-label-lg' => $read === null, 'text-label-md' => $read !== null])>{{ $announcement->title() }}</a>
                        @if ($read === null)
                            <x-badge tone="primary" dot>{{ __('announcements.portal.new') }}</x-badge>
                        @endif
                    </div>
                    <p class="text-body-sm text-text-secondary">{{ $announcement->summary() }}</p>
                    <div class="flex flex-wrap items-center gap-2 text-caption-md text-text-tertiary">
                        <x-badge :tone="$announcement->type->tone()">{{ $announcement->type->label() }}</x-badge>
                        @if ($announcement->requires_acknowledgement)
                            <x-badge :tone="$read?->acknowledged_at ? 'success' : 'warning'">{{ $read?->acknowledged_at ? __('announcements.show.acknowledged_on', ['date' => \App\Support\Formatter::date($read->acknowledged_at)]) : __('announcements.portal.requires_acknowledgement') }}</x-badge>
                        @endif
                        @if ($announcement->attachments_count)
                            <span class="inline-flex items-center gap-1"><x-icon name="paperclip" size="sm" /> {{ $announcement->attachments_count }}</span>
                        @endif
                        <x-date :value="$announcement->publish_at ?? $announcement->published_at" />
                    </div>
                </li>
            @empty
                <li class="px-5 py-6"><x-empty-state icon="megaphone" :title="$unreadOnly ? __('announcements.portal.empty_unread') : __('announcements.portal.empty')" /></li>
            @endforelse
        </ul>
        <x-slot:footer>
            <x-pagination :paginator="$announcements" />
        </x-slot:footer>
    </x-card>
@endsection
