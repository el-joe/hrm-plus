<?php

namespace App\Services\Notifications\Push;

use App\Models\DeviceToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Firebase Cloud Messaging, HTTP v1 API, without an SDK:
 *
 * 1. a JWT signed with the service account's RSA key (openssl, RS256) is exchanged at the token URI for an
 *    OAuth access token (scope firebase.messaging), cached for 50 minutes;
 * 2. one POST per device to https://fcm.googleapis.com/v1/projects/{project_id}/messages:send.
 *
 * Tokens FCM reports as UNREGISTERED / invalid are returned for deletion. 429 / 5xx are retryable.
 * Delivery is untested until the client supplies a Firebase project (blocker B5).
 */
class FcmHttpV1PushChannel implements PushChannel
{
    public const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * @param  array{project_id: string, client_email: string, private_key: string, token_uri?: string}  $credentials
     */
    public function __construct(
        private readonly array $credentials,
        private readonly string $androidChannelId = 'hrm_plus_default',
        private readonly int $ttlSeconds = 86400,
    ) {}

    public function name(): string
    {
        return 'fcm';
    }

    public function isSimulated(): bool
    {
        return false;
    }

    /**
     * Validate a service-account JSON document; returns the decoded credentials.
     *
     * @return array{project_id: string, client_email: string, private_key: string, token_uri: string}
     *
     * @throws RuntimeException
     */
    public static function parseCredentials(string $json): array
    {
        $data = json_decode($json, true);

        if (! is_array($data) || ($data['type'] ?? null) !== 'service_account'
            || ! filled($data['project_id'] ?? null) || ! filled($data['client_email'] ?? null) || ! filled($data['private_key'] ?? null)) {
            throw new RuntimeException('not_service_account');
        }

        if (openssl_pkey_get_private($data['private_key']) === false) {
            throw new RuntimeException('invalid_private_key');
        }

        return [
            'project_id' => (string) $data['project_id'],
            'client_email' => (string) $data['client_email'],
            'private_key' => (string) $data['private_key'],
            'token_uri' => (string) ($data['token_uri'] ?? 'https://oauth2.googleapis.com/token'),
        ];
    }

    public function send(Collection $devices, PushMessage $message): PushResult
    {
        try {
            $accessToken = $this->accessToken();
        } catch (ConnectionException|RuntimeException $exception) {
            return new PushResult(sent: 0, failed: $devices->count(), error: 'auth: '.$exception->getMessage(), retryable: true);
        }

        $sent = 0;
        $failed = 0;
        $invalid = [];
        $ids = [];
        $error = null;
        $retryable = false;
        $url = 'https://fcm.googleapis.com/v1/projects/'.$this->credentials['project_id'].'/messages:send';

        foreach ($devices as $device) {
            try {
                $response = Http::withToken($accessToken)->acceptJson()->timeout(10)->post($url, ['message' => $this->payload($device, $message)]);
            } catch (ConnectionException $exception) {
                $failed++;
                $error = $exception->getMessage();
                $retryable = true;

                continue;
            }

            if ($response->successful()) {
                $sent++;
                $ids[] = (string) $response->json('name');

                continue;
            }

            $failed++;
            $status = (string) $response->json('error.status');
            $error = trim($status.' '.$response->json('error.message'));

            if ($response->status() === 404 || $status === 'UNREGISTERED'
                || ($status === 'INVALID_ARGUMENT' && str_contains((string) $response->json('error.message'), 'token'))) {
                $invalid[] = $device->id;
            } elseif ($response->status() === 429 || $response->serverError()) {
                $retryable = true;
            }
        }

        return new PushResult($sent, $failed, $invalid, $ids, $error, retryable: $retryable && $sent === 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DeviceToken $device, PushMessage $message): array
    {
        $aps = ['sound' => 'default'];

        if ($message->badge !== null) {
            $aps['badge'] = $message->badge;
        }

        return [
            'token' => $device->token,
            'notification' => ['title' => $message->title, 'body' => $message->body],
            'data' => array_map('strval', $message->data),
            'android' => [
                'priority' => 'high',
                'ttl' => $this->ttlSeconds.'s',
                'notification' => ['channel_id' => $this->androidChannelId, 'click_action' => 'FLUTTER_NOTIFICATION_CLICK'],
            ],
            'apns' => [
                'headers' => ['apns-priority' => '10', 'apns-expiration' => (string) (time() + $this->ttlSeconds)],
                'payload' => ['aps' => $aps],
            ],
        ];
    }

    /**
     * @throws ConnectionException|RuntimeException
     */
    private function accessToken(): string
    {
        $cacheKey = 'fcm.access_token.'.sha1($this->credentials['client_email'].$this->credentials['project_id']);

        return Cache::remember($cacheKey, now()->addMinutes(50), function (): string {
            $response = Http::asForm()->timeout(10)->post($this->credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->signedJwt(),
            ]);

            if (! $response->successful() || ! filled($response->json('access_token'))) {
                throw new RuntimeException('token exchange failed ('.$response->status().')');
            }

            return (string) $response->json('access_token');
        });
    }

    /**
     * @throws RuntimeException
     */
    private function signedJwt(): string
    {
        $now = time();
        $segments = [
            $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64Url((string) json_encode([
                'iss' => $this->credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $this->credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];

        $signature = '';

        if (! openssl_sign(implode('.', $segments), $signature, $this->credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('signing failed');
        }

        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
