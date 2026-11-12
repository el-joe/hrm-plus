{{-- Mail settings: test send with the saved settings (log mailer until SMTP is configured, blocker B6). --}}
@if (($extras['mailer'] ?? null) === 'log')
    <x-alert tone="warning" data-notice="mail-log">{{ __('notifications.deliveries.mail_log_notice') }}</x-alert>
@endif

@if ($canManage)
    <x-card :title="__('notifications.settings.mail_test_title')" :subtitle="__('notifications.settings.mail_test_help')" data-region="mail-test">
        <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end" data-validate>
            @csrf
            <div class="min-w-0 flex-1">
                <x-form.input name="to" id="mail-test-to" type="email" :label="__('notifications.settings.mail_test_to')" required maxlength="255"
                    :value="old('to', $extras['testTo'] ?? '')" dir="ltr" />
            </div>
            <x-button icon="send">{{ __('notifications.settings.mail_test_send') }}</x-button>
        </form>
    </x-card>
@endif
