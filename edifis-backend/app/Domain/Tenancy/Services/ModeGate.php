<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Services;

/** Determines whether a cloud-only feature is available in the current run mode. ADR-004. */
class ModeGate
{
    public static function cloud(): bool
    {
        return config('edifis.mode') === 'cloud';
    }

    public static function local(): bool
    {
        return config('edifis.mode') === 'local';
    }

    /** Cloud-only multi-tenancy is active. On a local node, tenancy middleware + bootstrappers are bypassed. */
    public static function tenancyEnabled(): bool
    {
        return static::cloud();
    }

    /** Throws NodeModeUnsupportedException if a cloud-only feature is called on a node. */
    public function requireFeature(string $feature): void
    {
        if (! static::cloud()) {
            throw new \App\Exceptions\NodeModeUnsupportedException(
                "Feature '{$feature}' requires cloud mode."
            );
        }
    }
}
