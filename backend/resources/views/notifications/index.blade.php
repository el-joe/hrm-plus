{{-- Notification centre list, shared by the admin area and the portal ($area: admin | portal). --}}
@extends($area === 'admin' ? 'layouts.admin' : 'layouts.employee', ['subtitle' => __('notifications.subtitle')])

@section('title', __('notifications.title'))

@section('page-actions')
    <x-button variant="secondary" :href="route($area.'.notification-preferences.edit')" icon="settings">{{ __('notifications.preferences_link') }}</x-button>
    @if ($unreadCount)
        <form method="POST" action="{{ route($area.'.notifications.read-all') }}">
            @csrf
            <x-button icon="check">{{ __('notifications.mark_all_read') }}</x-button>
        </form>
    @endif
@endsection

@section('content')
    <x-tabs :label="__('notifications.title')" :items="[
        ['label' => __('notifications.filter_all'), 'url' => route($area.'.notifications.index'), 'active' => ! $unreadOnly],
        ['label' => __('notifications.filter_unread').($unreadCount ? ' ('.$unreadCount.')' : ''), 'url' => route($area.'.notifications.index', ['filter' => 'unread']), 'active' => $unreadOnly],
    ]" />

    <x-card padding="none" data-region="notifications">
        <ul class="divide-y divide-border-subtle">
            @forelse ($notifications as $item)
                <li @class(['flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start sm:px-5', 'bg-action-primary-subtle/40' => ! $item['read']]) data-notification="{{ $item['id'] }}">
                    <span @class(['mt-1.5 hidden size-2.5 shrink-0 rounded-full sm:block', 'bg-action-primary-default' => ! $item['read'], 'bg-border-default' => $item['read']]) aria-hidden="true"></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route($area.'.notifications.open', $item['id']) }}" class="text-label-lg text-text-primary hover:text-text-link hover:underline" dir="auto">{{ $item['title'] }}</a>
                            @unless ($item['read'])
                                <x-badge tone="primary" dot>{{ __('notifications.unread') }}</x-badge>
                            @endunless
                        </div>
                        <p class="mt-1 whitespace-pre-line text-body-md text-text-secondary" dir="auto">{{ $item['message'] }}</p>
                        <p class="mt-1 text-caption-md text-text-tertiary">
                            @if ($item['module'])
                                {{ __('notifications.modules.'.$item['module']) }} ·
                            @endif
                            <time datetime="{{ $item['created_at']->toIso8601String() }}" title="{{ $item['created_at']->toDateTimeString() }}">{{ $item['created_at']->locale(app()->getLocale())->diffForHumans() }}</time>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        @if ($item['url'])
                            <x-button size="sm" variant="secondary" :href="route($area.'.notifications.open', $item['id'])" icon-end="chevron-right">{{ __('notifications.open') }}</x-button>
                        @endif
                        @unless ($item['read'])
                            <form method="POST" action="{{ route($area.'.notifications.read', $item['id']) }}">
                                @csrf
                                <x-icon-button type="submit" icon="check" :label="__('notifications.mark_read')" />
                            </form>
                        @endunless
                    </div>
                </li>
            @empty
                <li class="px-5 py-8"><x-empty-state icon="bell" :title="__('notifications.empty_title')" :description="$unreadOnly ? __('notifications.empty_unread') : __('notifications.empty')" /></li>
            @endforelse
        </ul>
        <x-slot:footer>
            <x-pagination :paginator="$notifications" />
        </x-slot:footer>
    </x-card>
@endsection
