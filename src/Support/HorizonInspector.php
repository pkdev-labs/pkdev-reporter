<?php

namespace Pkdev\Reporter\Support;

class HorizonInspector
{
    /**
     * Returns Horizon status if installed, null otherwise.
     */
    public function status(): ?array
    {
        if (!class_exists(\Laravel\Horizon\Horizon::class)) { return null; }

        try {
            $masterSupervisor = app(\Laravel\Horizon\MasterSupervisor::class);

            return [
                'status'    => \Laravel\Horizon\Contracts\MasterSupervisorRepository::class
                    ? $this->horizonStatus()
                    : 'unknown',
                'processes' => $this->horizonProcessCount(),
            ];
        } catch (\Throwable) {
            return ['status' => 'unknown', 'processes' => 0];
        }
    }

    private function horizonStatus(): string
    {
        try {
            $repository = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class);
            $masters    = $repository->all();
            if (empty($masters)) { return 'inactive'; }

            return collect($masters)->every(fn ($m) => $m->status === 'running')
                ? 'running'
                : 'paused';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    private function horizonProcessCount(): int
    {
        try {
            $repository = app(\Laravel\Horizon\Contracts\SupervisorRepository::class);

            return collect($repository->all())->sum(fn ($s) => count($s->processes));
        } catch (\Throwable) {
            return 0;
        }
    }
}
