<?php

namespace Pkdev\Reporter\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class PkdevClient
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client([
            'base_uri' => rtrim(config('pkdev-reporter.api_url', ''), '/'),
            'timeout'  => config('pkdev-reporter.http_timeout', 5),
            'headers'  => [
                'Authorization' => 'Bearer '.config('pkdev-reporter.site_key'),
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    /**
     * POST payload to the PKDev API endpoint.
     * Never throws — logs failures and returns false.
     */
    public function post(string $endpoint, array $payload): bool
    {
        $retries = config('pkdev-reporter.http_retries', 2);
        $delay   = config('pkdev-reporter.http_retry_delay_ms', 500);

        $body      = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->signingKey());

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            try {
                $response = $this->http->post($endpoint, [
                    'body'    => $body,
                    'headers' => ['X-PKDev-Signature' => $signature],
                ]);

                return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
            } catch (GuzzleException $e) {
                if ($attempt < $retries) {
                    usleep($delay * 1000 * (2 ** $attempt));
                    continue;
                }

                Log::warning('[pkdev/reporter] Failed to POST to '.$endpoint.': '.$e->getMessage());
            } catch (\Throwable $e) {
                Log::warning('[pkdev/reporter] Unexpected error posting to '.$endpoint.': '.$e->getMessage());
                break;
            }
        }

        return false;
    }

    public function ping(): bool
    {
        return $this->post('/api/v1/heartbeat', [
            'reported_at' => now()->utc()->toIso8601String(),
            'environment' => config('app.env'),
            'queues'      => [],
            'horizon'     => null,
        ]);
    }

    private function signingKey(): string
    {
        return hash('sha256', (string) config('pkdev-reporter.site_key'));
    }
}
