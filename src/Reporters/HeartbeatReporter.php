<?php

namespace Pkdev\Reporter\Reporters;

use Pkdev\Reporter\Http\PkdevClient;
use Pkdev\Reporter\Support\HorizonInspector;
use Pkdev\Reporter\Support\QueueInspector;

class HeartbeatReporter
{
    public function __construct(
        private readonly PkdevClient $client,
        private readonly QueueInspector $queues,
        private readonly HorizonInspector $horizon,
    ) {}

    public function report(): bool
    {
        if (!config('pkdev-reporter.features.heartbeat')) { return true; }

        return $this->client->post('/api/v1/heartbeat', $this->payload());
    }

    /**
     * Send the heartbeat and return the hub's status code; null when the feature is off or the hub is unreachable.
     */
    public function send(): ?int
    {
        if (!config('pkdev-reporter.features.heartbeat')) { return null; }

        return $this->client->send('/api/v1/heartbeat', $this->payload());
    }

    private function payload(): array
    {
        $queueNames = array_filter(array_map(
            'trim',
            explode(',', config('pkdev-reporter.queues', 'default'))
        ));

        return [
            'reported_at' => now()->utc()->toIso8601String(),
            'environment' => config('app.env'),
            'queues' => array_values(array_map(
                fn (string $queue) => $this->queues->inspect($queue),
                $queueNames
            )),
            'horizon' => $this->horizon->status(),
        ];
    }
}
