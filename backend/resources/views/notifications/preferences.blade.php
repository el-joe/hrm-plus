{{-- Notification preferences (event × channel), shared by the admin area and the portal ($area). --}}
@extends($area === 'admin' ? 'layouts.admin' : 'layouts.employee', [
    'subtitle' => __('notifications.preferences.subtitle'),
    'breadcrumbs' => [['label' => __('notifications.title'), 'url' => route($area.'.notifications.index')]],
])

@section('title', __('notifications.preferences.title'))

@section('content')
    <x-alert tone="info">{{ __('notifications.preferences.help', ['email' => $user->email ?: '—']) }}</x-alert>
    @unless ($mailEnabled)
        <x-alert tone="warning" data-notice="mail-off">{{ __('notifications.preferences.mail_off') }}</x-alert>
    @endunless
    @unless ($pushEnabled)
        <x-alert tone="warning" data-notice="push-off">{{ __('notifications.preferences.push_off') }}</x-alert>
    @endunless

    <form method="POST" action="{{ route($area.'.notification-preferences.update') }}" class="flex flex-col gap-4 lg:gap-6" data-region="preferences">
        @csrf
        @method('PUT')

        @foreach ($matrix as $module => $events)
            <x-card padding="none" :title="__('notifications.modules.'.$module)">
                <div class="overflow-x-auto">
                    <table class="w-full text-body-md">
                        <thead class="hidden bg-surface-muted text-start text-label-sm text-text-secondary sm:table-header-group">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 text-start font-medium">{{ __('notifications.preferences.event') }}</th>
                                @foreach ($channels as $channel)
                                    <th scope="col" class="w-28 px-3 py-2.5 text-center font-medium">{{ __('notifications.channels.'.$channel) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach ($events as $event => $row)
                                <tr class="flex flex-col gap-2 px-4 py-3 sm:table-row sm:p-0" data-preference="{{ $event }}">
                                    <th scope="row" class="text-start font-normal sm:px-5 sm:py-3">
                                        <span class="text-label-md text-text-primary">{{ $row['label'] }}</span>
                                        @if ($row['mandatory'])
                                            <x-badge tone="neutral" class="ms-1" :title="__('notifications.preferences.mandatory_help')">{{ __('notifications.preferences.mandatory') }}</x-badge>
                                        @endif
                                    </th>
                                    @foreach ($channels as $channel)
                                        @php($fieldId = 'pref-'.$event.'-'.$channel)
                                        <td class="sm:px-3 sm:py-3 sm:text-center">
                                            <label for="{{ $fieldId }}" class="inline-flex items-center gap-2 sm:justify-center">
                                                @unless ($row['mandatory'])
                                                    <input type="hidden" name="preferences[{{ $event }}][{{ $channel }}]" value="0">
                                                @endunless
                                                <input id="{{ $fieldId }}" type="checkbox" value="1" name="preferences[{{ $event }}][{{ $channel }}]"
                                                    class="size-4.5 cursor-pointer rounded-xs border border-border-strong accent-action-primary-default focus-visible:shadow-focus disabled:cursor-not-allowed"
                                                    @checked($row['channels'][$channel]) @disabled($row['mandatory'])>
                                                <span class="text-body-sm text-text-secondary sm:sr-only">{{ __('notifications.channels.'.$channel) }}</span>
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endforeach

        <x-form.actions>
            <x-button variant="secondary" :href="route($area.'.notifications.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>

    <x-card :title="__('notifications.preferences.devices')" :subtitle="__('notifications.preferences.devices_help')" data-region="devices">
        <ul class="divide-y divide-border-subtle">
            @forelse ($devices as $device)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-icon name="smartphone" class="text-text-tertiary" />
                        <div class="min-w-0">
                            <p class="truncate text-label-md text-text-primary">{{ $device->device_name ?: ucfirst($device->platform) }} <span class="text-text-tertiary">· {{ ucfirst($device->platform) }}</span></p>
                            <p class="text-caption-md text-text-tertiary">
                                @if ($device->app_version) <span dir="ltr">v{{ $device->app_version }}</span> · @endif
                                {{ __('notifications.preferences.device_last_seen', ['date' => $device->last_seen_at?->locale(app()->getLocale())->diffForHumans() ?? '—']) }}
                            </p>
                        </div>
                    </div>
                    @if ($area === 'portal')
                        <form method="POST" action="{{ route('portal.notification-preferences.devices.destroy', $device->id) }}" data-confirm="{{ __('notifications.preferences.remove_device') }}?" data-confirm-tone="danger">
                            @csrf
                            @method('DELETE')
                            <x-button size="sm" variant="ghost" icon="trash">{{ __('notifications.preferences.remove_device') }}</x-button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="text-body-sm text-text-tertiary">{{ __('notifications.preferences.no_devices') }}</li>
            @endforelse
        </ul>
    </x-card>
@endsection
