<?php

namespace Pkdev\Reporter\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QueueInspector
{
    /**
     * Inspect a single queue and return depth, oldest job age, and failed count.
     */
    public function inspect(string $queue): array
    {
        return [
            'name'               => $queue,
            'depth'              => $this->depth($queue),
            'oldest_job_seconds' => $this->oldestJobSeconds($queue),
            'failed_count'       => $this->failedCount(),
        ];
    }

    private function depth(string $queue): int
    {
        try {
            return match (config('queue.default')) {
                'redis'    => $this->redisDepth($queue),
                'database' => $this->databaseDepth($queue),
                default    => 0,
            };
        } catch (\Throwable) {
            return 0;
        }
    }

    private function redisDepth(string $queue): int
    {
        $connection = config('queue.connections.redis.connection', 'default');
        $prefix     = config('database.redis.'.$connection.'.options.prefix', '');
        $key        = $prefix.'queues:'.$queue;

        return (int) Redis::llen($key);
    }

    private function databaseDepth(string $queue): int
    {
        return DB::table('jobs')
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->count();
    }

    private function oldestJobSeconds(string $queue): ?int
    {
        try {
            return match (config('queue.default')) {
                'database' => $this->databaseOldestJobSeconds($queue),
                default    => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    private function databaseOldestJobSeconds(string $queue): ?int
    {
        $oldest = DB::table('jobs')
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->min('created_at');

        return $oldest ? (int) (time() - $oldest) : null;
    }

    private function failedCount(): int
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                return 0;
            }

            return DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
