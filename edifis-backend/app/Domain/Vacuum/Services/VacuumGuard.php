<?php

declare(strict_types=1);

namespace App\Domain\Vacuum\Services;

use App\Models\User;

class VacuumGuard
{
    const FINANCE_TARGETS = ['ledger_entry', 'issue_event', 'fees'];

    public function requirePrincipal(User $user): void
    {
        if (! $user->hasRole('principal')) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                'VACUUM requires the Principal role.'
            );
        }
    }

    public function requireNonFinance(string $targetType): void
    {
        if (in_array($targetType, self::FINANCE_TARGETS, true)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                'Finance targets are not editable via VACUUM.'
            );
        }
    }

    public function requireConfirm(string $command, bool $confirmed): void
    {
        $needsConfirm = ['bulk', 'destructive', 'deactivate_account'];

        if (in_array($command, $needsConfirm, true) && ! $confirmed) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                422,
                'This command requires explicit confirmation.'
            );
        }
    }
}
