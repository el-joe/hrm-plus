{{--
    Notification bell of the admin top bar and the portal header. $area: admin | portal.
    $bellUnread / $bellItems come from the view composer in AppServiceProvider (own notifications only).
    Works without JS (<details> menu, links, POST form); resources/js/ui/notification-bell.js refreshes the
    unread badge every minute.
--}}
<details class="relative" data-dropdown data-notification-bell data-count-url="{{ route($area.'.notifications.unread-count') }}">
    <summary class="relative inline-flex size-10 cursor-pointer list-none items-center justify-center rounded-md text-text-secondary transition-colors hover:bg-action-neutral-hover hover:text-text-primary"
        aria-label="{{ $bellUnread ? __('notifications.bell_unread', ['count' => $bellUnread]) : __('notifications.bell') }}" title="{{ __('notifications.bell') }}">
        <x-icon name="bell" />
        <span data-bell-count @class([
            'absolute -top-0.5 -end-0.5 min-w-5 rounded-full bg-feedback-danger-solid px-1 text-center text-caption-sm leading-5 text-text-on-primary tabular-nums',
            'hidden' => $bellUnread === 0,
        ])>{{ $bellUnread > 99 ? '99+' : $bellUnread }}</span>
    </summary>
    <div role="menu" class="absolute end-0 top-full z-50 mt-2 w-80 overflow-hidden max-sm:fixed max-sm:inset-x-4 max-sm:top-16 max-sm:mt-0 max-sm:w-auto rounded-lg border border-border-subtle bg-surface-raised shadow-lg">
        <div class="flex items-center justify-between gap-2 border-b border-border-subtle px-4 py-3">
            <p class="text-label-lg text-text-primary">{{ __('notifications.title') }}</p>
            @if ($bellUnread)
                <form method="POST" action="{{ route($area.'.notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="text-label-sm text-text-link hover:underline">{{ __('notifications.mark_all_read') }}</button>
                </form>
            @endif
        </div>
        <ul class="max-h-96 divide-y divide-border-subtle overflow-y-auto">
            @forelse ($bellItems as $item)
                <li>
                    <a href="{{ route($area.'.notifications.open', $item['id']) }}" role="menuitem"
                        class="flex gap-3 px-4 py-3 transition-colors hover:bg-action-neutral-hover">
                        <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-action-primary-default' => ! $item['read'], 'bg-transparent' => $item['read']]) aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span @class(['block truncate text-label-md', 'text-text-primary' => ! $item['read'], 'text-text-secondary' => $item['read']]) dir="auto">{{ $item['title'] }}</span>
                            <span class="line-clamp-2 block text-body-sm text-text-secondary" dir="auto">{{ $item['message'] }}</span>
                            <span class="block text-caption-sm text-text-tertiary"><time datetime="{{ $item['created_at']->toIso8601String() }}">{{ $item['created_at']->locale(app()->getLocale())->diffForHumans() }}</time></span>
                        </span>
                    </a>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-body-sm text-text-tertiary">{{ __('notifications.empty') }}</li>
            @endforelse
        </ul>
        <div class="flex items-center justify-between gap-2 border-t border-border-subtle px-4 py-2.5">
            <a href="{{ route($area.'.notifications.index') }}" class="text-label-md text-text-link hover:underline">{{ __('notifications.view_all') }}</a>
            <a href="{{ route($area.'.notification-preferences.edit') }}" class="inline-flex size-8 items-center justify-center rounded-md text-text-secondary hover:bg-action-neutral-hover"
                aria-label="{{ __('notifications.preferences_link') }}" title="{{ __('notifications.preferences_link') }}"><x-icon name="settings" size="sm" /></a>
        </div>
    </div>
</details>
