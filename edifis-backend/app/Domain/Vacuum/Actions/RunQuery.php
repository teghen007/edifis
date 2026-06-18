<?php

declare(strict_types=1);

namespace App\Domain\Vacuum\Actions;

use App\Domain\Vacuum\Services\QueryPlanner;
use App\Models\User;

class RunQuery
{
    public function __construct(private readonly QueryPlanner $planner) {}

    public function handle(User $user, string $question): array
    {
        if (! $user->hasRole('principal')) {
            return [
                'answer' => 'Access denied. VACUUM queries require the Principal role.',
                'records' => [],
            ];
        }

        return $this->planner->plan($question);
    }
}
