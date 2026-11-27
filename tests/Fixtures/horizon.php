<?php

/**
 * The slice of Laravel Horizon that HorizonInspector touches, for tests that run in their own
 * process so the "Horizon is not installed" tests never see these classes.
 */

namespace Laravel\Horizon {
    class Horizon {}
}

namespace Laravel\Horizon\Contracts {
    interface MasterSupervisorRepository
    {
        public function all();
    }

    interface SupervisorRepository
    {
        public function all();
    }
}
