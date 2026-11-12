@extends('layouts.employee')

@section('title', $announcement->title())

@section('page-actions')
    <x-button variant="secondary" icon="arrow-left" :href="route('portal.announcements.index')">{{ __('announcements.portal.back') }}</x-button>
@endsection

@section('content')
    @php
        $locale = filled($announcement->{'body_'.app()->getLocale()}) ? app()->getLocale() : (app()->getLocale() === 'ar' ? 'en' : 'ar');
    @endphp
    <x-card data-region="announcement">
        <div class="mb-4 flex flex-wrap items-center gap-2 text-caption-md text-text-tertiary">
            <x-badge :tone="$announcement->type->tone()">{{ $announcement->type->label() }}</x-badge>
            @if ($announcement->is_pinned) <x-badge tone="primary">{{ __('announcements.portal.pinned') }}</x-badge> @endif
            <span dir="ltr">{{ ($announcement->publish_at ?? $announcement->published_at)?->format('Y-m-d H:i') }}</span>
        </div>
        @include('admin.announcements._body', ['html' => $announcement->body($locale), 'direction' => config('app.supported_locales.'.$locale, 'rtl'), 'locale' => $locale])

        @if ($announcement->attachments->isNotEmpty())
            <h2 class="mt-6 mb-2 text-label-lg text-text-primary">{{ __('announcements.portal.attachments') }}</h2>
            <ul class="flex flex-col gap-2" data-region="attachments">
                @foreach ($announcement->attachments as $attachment)
                    <li class="flex items-center gap-2">
                        <x-icon name="paperclip" size="sm" class="shrink-0 text-text-tertiary" />
                        <a class="min-w-0 truncate text-body-sm text-text-link hover:underline" href="{{ route('portal.announcements.attachments.download', [$announcement->id, $attachment->id]) }}">{{ $attachment->file_name }}</a>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($announcement->requires_acknowledgement)
            <x-slot:footer>
                @if ($read->acknowledged_at)
                    <p class="flex items-center gap-2 text-body-sm text-feedback-success-text" data-notice="acknowledged">
                        <x-icon name="check-circle" size="sm" /> {{ __('announcements.portal.acknowledged_on', ['date' => $read->acknowledged_at->format('Y-m-d H:i')]) }}
                    </p>
                @else
                    <form method="POST" action="{{ route('portal.announcements.acknowledge', $announcement->id) }}" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        @csrf
                        <p class="text-body-sm text-text-secondary">{{ __('announcements.portal.acknowledge_help') }}</p>
                        <x-button icon="check">{{ __('announcements.portal.acknowledge') }}</x-button>
                    </form>
                @endif
            </x-slot:footer>
        @endif
    </x-card>
@endsection
