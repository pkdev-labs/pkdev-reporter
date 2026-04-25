<?php

namespace Pkdev\Reporter\Reporters;

use Pkdev\Reporter\Http\PkdevClient;
use Pkdev\Reporter\Support\QueueInspector;
use Pkdev\Reporter\Support\HorizonInspector;

class HeartbeatReporter
{
    public function __construct(
        private readonly PkdevClient $client,
        private readonly QueueInspector $queues,
        private readonly HorizonInspector $horizon,
    ) {}

    public function report(): bool
    {
        if (!config('pkdev-reporter.features.heartbeat')) {
            return true;
        }

        $queueNames = array_filter(array_map(
            'trim',
            explode(',', config('pkdev-reporter.queues', 'default'))
        ));

        $payload = [
            'reported_at' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'queues'      => array_map(
                fn (string $queue) => $this->queues->inspect($queue),
                $queueNames
            ),
            'horizon'     => $this->horizon->status(),
        ];

        return $this->client->post('/api/v1/heartbeat', $payload);
    }
}
