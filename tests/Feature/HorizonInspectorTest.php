<?php

namespace Pkdev\Reporter\Tests\Feature;

use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Horizon;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Pkdev\Reporter\Support\HorizonInspector;
use Pkdev\Reporter\Tests\TestCase;
use RuntimeException;

#[RunTestsInSeparateProcesses]
class HorizonInspectorTest extends TestCase
{
    private function installHorizon(array $masterStatuses, array $processCounts): void
    {
        require_once __DIR__.'/../Fixtures/horizon.php';

        $this->app->instance(MasterSupervisorRepository::class, new class(array_map(fn (string $status) => (object) ['status' => $status], $masterStatuses)) implements MasterSupervisorRepository {
            public function __construct(private readonly array $masters) {}

            public function all()
            {
                return $this->masters;
            }
        });

        $this->app->instance(SupervisorRepository::class, new class(array_map(fn (int $count) => (object) ['processes' => array_fill(0, $count, 'pid')], $processCounts)) implements SupervisorRepository {
            public function __construct(private readonly array $supervisors) {}

            public function all()
            {
                return $this->supervisors;
            }
        });
    }

    public function test_a_site_without_horizon_reports_null(): void
    {
        $this->assertFalse(class_exists(Horizon::class));

        $this->assertNull((new HorizonInspector)->status());
    }

    public function test_running_horizon_reports_its_status_and_every_supervisors_processes(): void
    {
        $this->installHorizon(['running', 'running'], [3, 2]);

        $this->assertSame(['status' => 'running', 'processes' => 5], (new HorizonInspector)->status());
    }

    public function test_one_paused_master_makes_horizon_paused(): void
    {
        $this->installHorizon(['running', 'paused'], [1]);

        $this->assertSame(['status' => 'paused', 'processes' => 1], (new HorizonInspector)->status());
    }

    public function test_horizon_installed_but_not_started_is_inactive(): void
    {
        $this->installHorizon([], []);

        $this->assertSame(['status' => 'inactive', 'processes' => 0], (new HorizonInspector)->status());
    }

    public function test_horizon_whose_redis_is_down_reports_unknown_without_throwing(): void
    {
        require_once __DIR__.'/../Fixtures/horizon.php';
        $unreachable = new class implements MasterSupervisorRepository, SupervisorRepository {
            public function all()
            {
                throw new RuntimeException('Connection refused [tcp://127.0.0.1:6379]');
            }
        };
        $this->app->instance(MasterSupervisorRepository::class, $unreachable);
        $this->app->instance(SupervisorRepository::class, $unreachable);

        $this->assertSame(['status' => 'unknown', 'processes' => 0], (new HorizonInspector)->status());
    }
}
