<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CommunicationFcm
{
    public function send(string $token, object $item)
    {
        $path = config('communication_push.credentials');
        if (! $path || ! is_readable($path)) {
            throw new RuntimeException('FCM credentials unavailable');
        }
        $account = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $cacheKey = 'communication-oauth-'.hash('sha256', $account['private_key_id']);
        $access = Cache::remember($cacheKey, 3000, function () use ($account) {
            $encode = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $url = 'https://oauth2.googleapis.com/token';
            $claims = ['iss' => $account['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => $url, 'iat' => time(), 'exp' => time() + 3600];
            $jwt = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode(json_encode($claims));
            if (! openssl_sign($jwt, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('FCM signing failed');
            }
            $response = Http::asForm()->timeout(10)->post($url, ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt.'.'.$encode($signature)]);
            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException('FCM authentication failed');
            }

            return $response->json('access_token');
        });
        $response = Http::withToken($access)->timeout(15)->post('https://fcm.googleapis.com/v1/projects/'.rawurlencode($account['project_id']).'/messages:send', [
            'message' => ['token' => $token, 'data' => ['type' => 'communication', 'communication_id' => (string) $item->id, 'title' => $item->title, 'summary' => $item->summary]],
        ]);
        if ($response->unauthorized()) {
            Cache::forget($cacheKey);
        }

        return $response;
    }
}
