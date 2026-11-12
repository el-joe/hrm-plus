@extends('layouts.employee')

@section('title', __('app.account.title'))

@section('content')
    {{-- Change password --}}
    <form method="POST" action="{{ route('portal.account.password') }}" class="flex flex-col gap-4" data-validate data-region="account-password">
        @csrf
        @method('PUT')
        <x-form.section :title="__('app.account.password_title')" :description="__('app.account.password_help')">
            <div class="md:col-span-2 md:max-w-[calc(50%-0.75rem)]">
                <x-form.input type="password" name="current_password" :label="__('app.account.current_password')" autocomplete="current-password" required />
            </div>
            <x-form.input type="password" name="password" :label="__('app.account.new_password')" autocomplete="new-password" required
                :minlength="(int) setting('security.password_min_length')" :help="__('app.account.password_rules', ['min' => \App\Support\Formatter::number((int) setting('security.password_min_length'))])" />
            <x-form.input type="password" name="password_confirmation" :label="__('app.account.confirm_password')" autocomplete="new-password" required data-match="password" />
            <div class="md:col-span-2">
                <x-button icon="lock">{{ __('app.account.change_password') }}</x-button>
            </div>
        </x-form.section>
    </form>

    {{-- Language, theme, notifications --}}
    <x-form.section :title="__('app.account.preferences_title')" :description="__('app.account.preferences_help')" data-region="account-preferences">
        <x-form.field :label="__('app.account.language')">
            <div class="flex flex-wrap gap-2">
                @foreach (config('app.supported_locales') as $locale => $direction)
                    @if ($locale === app()->getLocale())
                        <span class="inline-flex h-10 items-center gap-2 rounded-md border border-action-primary-default bg-action-primary-subtle px-3 text-label-md text-text-link" aria-current="true">
                            <x-icon name="check" size="sm" /> {{ __('app.locales.'.$locale) }}
                        </span>
                    @else
                        <form method="POST" action="{{ route('locale.update') }}" data-locale-switcher>
                            @csrf
                            <input type="hidden" name="locale" value="{{ $locale }}">
                            <button type="submit" lang="{{ $locale }}" class="inline-flex h-10 items-center gap-2 rounded-md border border-border-default bg-surface-default px-3 text-label-md text-text-primary transition-colors hover:bg-action-neutral-hover">
                                <x-icon name="globe" size="sm" class="text-text-tertiary" /> {{ __('app.locales.'.$locale) }}
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>
            <p class="mt-1.5 text-caption-md text-text-tertiary">{{ __('app.account.language_help') }}</p>
        </x-form.field>

        <x-form.field :label="__('app.account.theme')">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" data-theme-toggle hidden data-js-only
                    class="inline-flex h-10 items-center gap-2 rounded-md border border-border-default bg-surface-default px-3 text-label-md text-text-primary transition-colors hover:bg-action-neutral-hover">
                    <x-icon name="moon" size="sm" class="dark:hidden" />
                    <x-icon name="sun" size="sm" class="hidden dark:block" />
                    <span class="dark:hidden">{{ __('app.account.theme_dark') }}</span>
                    <span class="hidden dark:inline">{{ __('app.account.theme_light') }}</span>
                </button>
            </div>
            <p class="mt-1.5 text-caption-md text-text-tertiary">{{ __('app.account.theme_help') }}</p>
        </x-form.field>

        <x-form.field :label="__('app.nav.notification_preferences')" class="md:col-span-2">
            <div>
                <x-button variant="secondary" size="sm" icon="bell" :href="route('portal.notification-preferences.edit')">{{ __('app.account.open_notification_settings') }}</x-button>
            </div>
        </x-form.field>
    </x-form.section>

    {{-- Where the account is signed in --}}
    <x-card :title="__('app.account.browsers_title')" :subtitle="__('app.account.browsers_help')" data-region="account-sessions">
        @if (! $tracksWebSessions)
            <p class="text-body-sm text-text-tertiary">{{ __('app.account.sessions_untracked') }}</p>
        @else
            <ul class="divide-y divide-border-subtle">
                @forelse ($webSessions as $session)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0" data-session>
                        <div class="flex min-w-0 items-center gap-3">
                            <x-icon name="globe" class="shrink-0 text-text-tertiary" />
                            <div class="min-w-0">
                                <p class="text-label-md text-text-primary">
                                    {{ $session['browser'] === '—' && $session['platform'] === '—' ? __('app.account.unknown_browser') : $session['browser'].' · '.$session['platform'] }}
                                    @if ($session['is_current'])<x-badge tone="success" class="ms-1">{{ __('app.account.this_device') }}</x-badge>@endif
                                </p>
                                <p class="text-caption-md text-text-tertiary">
                                    <span dir="ltr">{{ $session['ip_address'] ?? '—' }}</span>
                                    · {{ __('app.account.last_active') }} <x-date :value="$session['last_active_at']" format="datetime" />
                                </p>
                            </div>
                        </div>
                        @unless ($session['is_current'])
                            <form method="POST" action="{{ route('portal.account.sessions.destroy', $session['id']) }}"
                                data-confirm="{{ __('app.account.revoke_confirm') }}" data-confirm-tone="danger">
                                @csrf
                                @method('DELETE')
                                <x-button variant="secondary" size="sm" icon="log-out">{{ __('app.account.revoke') }}</x-button>
                            </form>
                        @endunless
                    </li>
                @empty
                    <li class="text-body-sm text-text-tertiary">{{ __('app.account.no_sessions') }}</li>
                @endforelse
            </ul>
        @endif
    </x-card>

    <x-card :title="__('app.account.devices_title')" :subtitle="__('app.account.devices_help')" data-region="account-devices">
        <ul class="divide-y divide-border-subtle">
            @forelse ($tokens as $token)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0" data-device>
                    <div class="flex min-w-0 items-center gap-3">
                        <x-icon name="smartphone" class="shrink-0 text-text-tertiary" />
                        <div class="min-w-0">
                            <p class="truncate text-label-md text-text-primary" dir="auto">{{ $token['name'] }}</p>
                            <p class="text-caption-md text-text-tertiary">
                                {{ __('app.account.last_active') }} <x-date :value="$token['last_used_at']" format="datetime" :empty="__('app.account.never_used')" />
                                · {{ __('app.account.signed_in_on') }} <x-date :value="$token['created_at']" />
                            </p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('portal.account.devices.destroy', $token['id']) }}"
                        data-confirm="{{ __('app.account.revoke_confirm') }}" data-confirm-tone="danger">
                        @csrf
                        @method('DELETE')
                        <x-button variant="secondary" size="sm" icon="log-out">{{ __('app.account.revoke') }}</x-button>
                    </form>
                </li>
            @empty
                <li class="text-body-sm text-text-tertiary">{{ __('app.account.no_devices') }}</li>
            @endforelse
        </ul>
    </x-card>

    <x-card :title="__('app.account.logins_title')" padding="none" data-region="account-logins">
        <x-table>
            <x-slot:head>
                <x-table.th>{{ __('app.account.login_when') }}</x-table.th>
                <x-table.th>{{ __('app.account.login_where') }}</x-table.th>
                <x-table.th>{{ __('app.account.login_result') }}</x-table.th>
            </x-slot:head>
            @forelse ($logins as $login)
                @php($agent = \App\Services\Auth\AccountSessions::describeAgent($login->user_agent))
                <x-table.row>
                    <x-table.td :label="__('app.account.login_when')"><x-date :value="$login->created_at" format="datetime" /></x-table.td>
                    <x-table.td :label="__('app.account.login_where')">
                        {{ __('app.account.areas.'.$login->area->value) }} · {{ $agent['browser'] }} · {{ $agent['platform'] }} · <span dir="ltr">{{ $login->ip_address }}</span>
                    </x-table.td>
                    <x-table.td :label="__('app.account.login_result')">
                        <x-badge :tone="$login->successful ? 'success' : 'danger'">{{ $login->successful ? __('app.account.login_ok') : __('app.account.login_failed') }}</x-badge>
                    </x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="3">{{ __('app.account.no_logins') }}</x-table.empty>
            @endforelse
        </x-table>
    </x-card>
@endsection
