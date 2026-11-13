<?php

namespace Pkdev\Reporter\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Pkdev\Reporter\Support\SiteKey;

class PkdevClient
{
    /**
     * POST to the hub and return its status code, or null when it could not be reached.
     * Retries transport failures and 5xx only. Never throws.
     */
    public function send(string $endpoint, array $payload): ?int
    {
        $retries = (int) config('pkdev-reporter.http_retries', 2);
        $delay = (int) config('pkdev-reporter.http_retry_delay_ms', 500);

        try {
            $options = $this->requestOptions($payload);
        } catch (\Throwable $exception) {
            Log::warning('[pkdev/reporter] Could not encode the payload for '.$endpoint.': '.$exception->getMessage());

            return null;
        }

        $url = rtrim((string) config('pkdev-reporter.api_url'), '/').$endpoint;

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            try {
                $status = $this->http()->request('POST', $url, $options)->getStatusCode();

                if ($status < 500 || $attempt === $retries) { return $status; }
            } catch (GuzzleException $exception) {
                if ($attempt === $retries) {
                    Log::warning('[pkdev/reporter] Failed to POST to '.$endpoint.': '.$exception->getMessage());

                    return null;
                }
            } catch (\Throwable $exception) {
                Log::warning('[pkdev/reporter] Unexpected error posting to '.$endpoint.': '.$exception->getMessage());

                return null;
            }

            usleep($delay * 1000 * (2 ** $attempt));
        }

        return null;
    }

    /**
     * POST payload to the PKDev API endpoint.
     * Never throws — logs failures and returns false.
     */
    public function post(string $endpoint, array $payload): bool
    {
        $status = $this->send($endpoint, $payload);

        if ($status !== null && $status >= 200 && $status < 300) { return true; }

        if ($status !== null && !$this->isExpectedRefusal($status)) {
            Log::warning('[pkdev/reporter] '.$endpoint.' answered '.$status);
        }

        return false;
    }

    public function ping(): bool
    {
        return $this->post('/api/v1/heartbeat', [
            'reported_at' => now()->utc()->toIso8601String(),
            'environment' => config('app.env'),
            'queues' => [],
            'horizon' => null,
        ]);
    }

    private function requestOptions(array $payload): array
    {
        $key = (string) SiteKey::current();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return [
            'body' => $body,
            'http_errors' => false,
            'timeout' => config('pkdev-reporter.http_timeout', 5),
            'headers' => [
                'Authorization' => 'Bearer '.$key,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-PKDev-Signature' => 'sha256='.hash_hmac('sha256', $body, hash('sha256', $key)),
            ],
        ];
    }

    private function isExpectedRefusal(int $status): bool
    {
        return $status === 403 || ($status === 401 && SiteKey::fromFile());
    }

    private function http(): ClientInterface
    {
        return app()->bound('pkdev-reporter.http') ? app('pkdev-reporter.http') : new Client;
    }
}
