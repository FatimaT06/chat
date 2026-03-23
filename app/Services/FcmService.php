<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private string $projectId;
    private string $credentialsPath;

    public function __construct()
    {
        $this->projectId       = config('services.fcm.project_id');
        $this->credentialsPath = config('services.fcm.credentials_path');
    }

    public function sendNotification(
        string $fcmToken,
        string $title,
        string $body,
        array  $data = []
    ): bool {
        try {
            $client = new GoogleClient();
            $client->setAuthConfig($this->credentialsPath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->fetchAccessTokenWithAssertion();
            $accessToken = $client->getAccessToken()['access_token'];

            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            $payload = [
                'message' => [
                    'token'        => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'data' => array_map('strval', $data),
                ],
            ];

            $response = Http::withToken($accessToken)->post($url, $payload);

            if (!$response->successful()) {
                Log::error('FCM error: ' . $response->body());
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            Log::error('FcmService exception: ' . $e->getMessage());
            return false;
        }
    }
}