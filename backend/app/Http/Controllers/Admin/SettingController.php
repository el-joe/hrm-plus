<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\User;
use App\Services\Notifications\Push\FcmHttpV1PushChannel;
use App\Services\Notifications\Push\PushChannelResolver;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SettingController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings.edit', $this->settings->groups()[0]);
    }

    public function edit(Request $request, string $group, PushChannelResolver $push): View
    {
        $definitions = $this->settings->formDefinitions($group);
        $secrets = [];

        foreach ($definitions as $name => $definition) {
            if ($definition['type'] === SettingType::Secret->value) {
                $secrets[$name] = $this->settings->hasSecret($group.'.'.$name);
            }
        }

        return view('admin.settings.edit', [
            'group' => $group,
            'groups' => $this->settings->groups(),
            'definitions' => $definitions,
            // Secret values never reach the view: only whether one is stored.
            'values' => array_diff_key($this->settings->group($group), $secrets),
            'secrets' => $secrets,
            'extras' => match ($group) {
                'mail' => ['testTo' => $request->user()->email, 'mailer' => config('mail.default')],
                'push' => $this->pushExtras($push),
                default => [],
            },
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pushExtras(PushChannelResolver $push): array
    {
        $credentials = null;

        try {
            $credentials = FcmHttpV1PushChannel::parseCredentials((string) $this->settings->get('push.fcm_service_account'));
        } catch (RuntimeException) {
            // No (valid) key stored.
        }

        return [
            'live' => $push->isLive(),
            'provider' => (string) $this->settings->get('push.provider'),
            'project' => $credentials['project_id'] ?? null,
            'clientEmail' => $credentials['client_email'] ?? null,
            'hasCredentials' => $this->settings->hasSecret('push.fcm_service_account'),
            'accounts' => User::query()->whereHas('deviceTokens')->withCount('deviceTokens')->orderBy('name')->limit(200)->get(['id', 'name', 'email']),
        ];
    }

    public function update(UpdateSettingsRequest $request, string $group): RedirectResponse
    {
        $this->settings->setMany($request->settings());

        return redirect()->route('admin.settings.edit', $group)->with('status', __('app.settings.saved'));
    }
}
