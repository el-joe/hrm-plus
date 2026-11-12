<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\Push\FcmHttpV1PushChannel;
use App\Services\Notifications\Push\PushChannelResolver;
use App\Services\Notifications\Push\PushMessage;
use App\Services\Settings\RuntimeSettings;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * The actions of the e-mail and push settings screens (settings.manage): send a test e-mail with the
 * saved mail settings, upload / remove the FCM service-account key (encrypted, never shown again), and
 * send a test push to an account's devices. Every action is audited; secrets never reach the log.
 */
class ChannelSettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings, private readonly AuditLogger $audit) {}

    public function mailTest(Request $request, RuntimeSettings $runtime): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email', 'max:255']]);
        $runtime->applyMail();
        $mailer = (string) config('mail.default');

        try {
            Mail::raw(__('notifications.settings.mail_test_body', ['app' => config('app.name'), 'name' => $request->user()->name]),
                fn ($message) => $message->to($data['to'])->subject(__('notifications.settings.mail_test_subject')));
        } catch (Throwable $exception) {
            $this->audit->log('settings.mail_test', meta: ['to' => $data['to'], 'mailer' => $mailer, 'ok' => false], actor: $request->user());

            return back()->withInput()->with('error', __('notifications.settings.mail_test_failed', ['error' => class_basename($exception).': '.$exception->getMessage()]));
        }

        $this->audit->log('settings.mail_test', meta: ['to' => $data['to'], 'mailer' => $mailer, 'ok' => true], actor: $request->user());

        return back()->with('status', $mailer === 'log'
            ? __('notifications.settings.mail_test_logged')
            : __('notifications.settings.mail_test_sent', ['email' => $data['to'], 'mailer' => $mailer]));
    }

    public function uploadCredentials(Request $request): RedirectResponse
    {
        $request->validate(['credentials' => ['required', 'file', 'max:20', 'mimetypes:application/json,text/plain']]);
        $json = (string) file_get_contents($request->file('credentials')->getRealPath());

        try {
            $credentials = FcmHttpV1PushChannel::parseCredentials($json);
        } catch (RuntimeException) {
            return back()->withErrors(['credentials' => __('notifications.settings.credentials_invalid')]);
        }

        $this->settings->set('push.fcm_service_account', $json);
        $this->audit->log('settings.push_credentials_uploaded', meta: ['project_id' => $credentials['project_id'], 'client_email' => $credentials['client_email']], actor: $request->user());

        return back()->with('status', __('notifications.settings.credentials_saved', ['project' => $credentials['project_id']]));
    }

    public function removeCredentials(Request $request): RedirectResponse
    {
        $this->settings->set('push.fcm_service_account', null);
        $this->audit->log('settings.push_credentials_removed', actor: $request->user());

        return back()->with('status', __('notifications.settings.credentials_removed'));
    }

    public function pushTest(Request $request, PushChannelResolver $providers): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $user = User::query()->whereKey($data['user_id'])->whereHas('deviceTokens')->first();

        if ($user === null) {
            return back()->withErrors(['user_id' => __('notifications.settings.push_test_none')]);
        }

        $devices = DeviceToken::query()->where('user_id', $user->id)->get();
        $provider = $providers->current();
        $result = $provider->send($devices, new PushMessage(
            __('notifications.settings.push_test_title_text', [], $user->locale),
            __('notifications.settings.push_test_body_text', [], $user->locale),
            ['event' => 'test', 'url' => route('portal.notifications.index')],
        ));

        if ($result->invalidDeviceIds !== []) {
            DeviceToken::query()->whereKey($result->invalidDeviceIds)->delete();
        }

        $status = $result->simulated ? __('notifications.delivery_statuses.simulated')
            : ($result->sent > 0 ? __('notifications.delivery_statuses.sent') : __('notifications.delivery_statuses.failed').($result->error ? ' ('.$result->error.')' : ''));
        $this->audit->log('settings.push_test', meta: ['user' => $user->id, 'provider' => $provider->name(), 'sent' => $result->sent, 'failed' => $result->failed], actor: $request->user());

        return back()->with($result->sent > 0 ? 'status' : 'error', __('notifications.settings.push_test_result', [
            'count' => $devices->count(), 'name' => $user->name, 'status' => $status,
        ]));
    }
}
