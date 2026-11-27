<?php

namespace Pkdev\Reporter\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Pkdev\Reporter\Support\QueueInspector;
use Pkdev\Reporter\Tests\TestCase;
use RuntimeException;

class QueueInspectorTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'queue_inspector');
        $app['config']->set('database.connections.queue_inspector', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('queue.default', 'database');
    }

    private function createQueueTables(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    private function queueJob(string $queue, int $createdSecondsAgo, bool $reserved = false): void
    {
        DB::table('jobs')->insert([
            'queue' => $queue,
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => $reserved ? time() : null,
            'available_at' => time(),
            'created_at' => time() - $createdSecondsAgo,
        ]);
    }

    private function failJob(string $queue): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => bin2hex(random_bytes(8)),
            'connection' => 'database',
            'queue' => $queue,
            'payload' => '{}',
            'exception' => 'RuntimeException',
        ]);
    }

    public function test_the_database_queue_reports_its_waiting_jobs_and_the_oldest_ones_age(): void
    {
        $this->createQueueTables();
        $this->queueJob('emails', createdSecondsAgo: 300);
        $this->queueJob('emails', createdSecondsAgo: 30);
        $this->queueJob('emails', createdSecondsAgo: 900, reserved: true);
        $this->queueJob('default', createdSecondsAgo: 3600);
        $this->failJob('emails');
        $this->failJob('default');

        $snapshot = (new QueueInspector)->inspect('emails');

        $this->assertSame('emails', $snapshot['name']);
        $this->assertSame(2, $snapshot['depth']);
        $this->assertEqualsWithDelta(300, $snapshot['oldest_job_seconds'], 2);
        $this->assertSame(2, $snapshot['failed_count']);
    }

    public function test_an_empty_database_queue_has_no_oldest_job(): void
    {
        $this->createQueueTables();

        $snapshot = (new QueueInspector)->inspect('default');

        $this->assertSame(0, $snapshot['depth']);
        $this->assertNull($snapshot['oldest_job_seconds']);
        $this->assertSame(0, $snapshot['failed_count']);
    }

    public function test_a_database_queue_whose_tables_are_missing_reads_as_an_empty_queue_without_throwing(): void
    {
        $snapshot = (new QueueInspector)->inspect('default');

        $this->assertSame(['name' => 'default', 'depth' => 0, 'oldest_job_seconds' => null, 'failed_count' => 0], $snapshot);
    }

    public function test_the_redis_queue_asks_the_client_for_the_unprefixed_key(): void
    {
        $this->createQueueTables();
        config(['queue.default' => 'redis', 'database.redis.options.prefix' => 'wyld_bee_meads_database_']);
        Redis::shouldReceive('llen')->once()->with('queues:emails')->andReturn(7);

        $snapshot = (new QueueInspector)->inspect('emails');

        $this->assertSame(7, $snapshot['depth']);
        $this->assertNull($snapshot['oldest_job_seconds']);
    }

    public function test_an_unreachable_redis_reads_as_an_empty_queue_without_throwing(): void
    {
        $this->createQueueTables();
        config(['queue.default' => 'redis']);
        Redis::shouldReceive('llen')->andThrow(new RuntimeException('Connection refused [tcp://127.0.0.1:6379]'));

        $snapshot = (new QueueInspector)->inspect('default');

        $this->assertSame(0, $snapshot['depth']);
        $this->assertNull($snapshot['oldest_job_seconds']);
    }

    public function test_a_sync_queue_has_no_depth_to_read(): void
    {
        $this->createQueueTables();
        config(['queue.default' => 'sync']);
        Redis::shouldReceive('llen')->never();

        $snapshot = (new QueueInspector)->inspect('default');

        $this->assertSame(0, $snapshot['depth']);
        $this->assertNull($snapshot['oldest_job_seconds']);
    }
}
