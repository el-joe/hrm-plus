{{-- Push settings: provider status (SIMULATED until a Firebase key is uploaded, blocker B5), key upload, test push. --}}
<x-card :title="__('notifications.settings.push_status')" data-region="push-status">
    @if ($extras['live'] ?? false)
        <x-alert tone="success">{{ __('notifications.settings.push_live', ['project' => $extras['project'], 'email' => $extras['clientEmail']]) }}</x-alert>
    @else
        <div class="flex flex-wrap items-center gap-2">
            @include('admin.attendance._simulated')
            <p class="text-body-sm text-text-secondary">
                {{ ($extras['provider'] ?? 'simulated') === 'fcm' ? __('notifications.settings.push_fcm_missing') : __('notifications.settings.push_simulated') }}
            </p>
        </div>
    @endif
</x-card>

@if ($canManage)
    <x-card :title="__('notifications.settings.credentials_title')" :subtitle="__('notifications.settings.credentials_help')" data-region="push-credentials">
        <p class="mb-3 text-body-sm text-text-secondary">
            {{ ($extras['hasCredentials'] ?? false) ? __('notifications.settings.secret_set') : __('notifications.settings.secret_not_set') }}
            @if ($extras['project'] ?? null)
                · <span dir="ltr">{{ $extras['project'] }}</span>
            @endif
        </p>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <form method="POST" action="{{ route('admin.settings.push.credentials') }}" enctype="multipart/form-data" class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="min-w-0 flex-1">
                    <x-form.file-input name="credentials" id="push-credentials" :label="__('notifications.settings.credentials_file')" accept="application/json,.json" required />
                </div>
                <x-button icon="upload">{{ __('notifications.settings.credentials_upload') }}</x-button>
            </form>
            @if ($extras['hasCredentials'] ?? false)
                <form method="POST" action="{{ route('admin.settings.push.credentials.destroy') }}"
                    data-confirm="{{ __('notifications.settings.credentials_remove_confirm') }}" data-confirm-tone="danger">
                    @csrf
                    @method('DELETE')
                    <x-button variant="danger" icon="trash">{{ __('notifications.settings.credentials_remove') }}</x-button>
                </form>
            @endif
        </div>
    </x-card>

    <x-card :title="__('notifications.settings.push_test_title')" :subtitle="__('notifications.settings.push_test_help')" data-region="push-test">
        @if (($extras['accounts'] ?? collect())->isEmpty())
            <p class="text-body-sm text-text-secondary">{{ __('notifications.settings.push_test_none') }}</p>
        @else
            <form method="POST" action="{{ route('admin.settings.push.test') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="min-w-0 flex-1">
                    <x-form.select name="user_id" id="push-test-user" :label="__('notifications.settings.push_test_user')" required>
                        @foreach ($extras['accounts'] as $account)
                            <option value="{{ $account->id }}">{{ $account->name }} — {{ $account->email }} ({{ trans_choice('notifications.deliveries.devices', $account->device_tokens_count, ['count' => $account->device_tokens_count]) }})</option>
                        @endforeach
                    </x-form.select>
                </div>
                <x-button icon="smartphone">{{ __('notifications.settings.push_test_send') }}</x-button>
            </form>
        @endif
    </x-card>
@endif
