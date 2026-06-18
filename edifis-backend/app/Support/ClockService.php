<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Clock discipline per white paper §5.1. Domain code never reads the raw system clock for cross-node ordering. */
class ClockService
{
    /** Device-local time. Use for display and local logic only. */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }

    /** Cloud-restamped authoritative time. Set only at sync; null until synced. */
    public function authoritativeStamp(): ?CarbonImmutable
    {
        return null; // cloud sets this on sync apply
    }
}
