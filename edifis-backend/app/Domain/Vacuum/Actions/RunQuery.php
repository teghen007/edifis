<?php

declare(strict_types=1);

namespace App\Domain\Vacuum\Actions;

use App\Domain\AI\Actions\PrincipalVacuum;
use App\Domain\AI\Services\LlmClient;
use App\Domain\Vacuum\Services\QueryPlanner;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class RunQuery
{
    public function __construct(
        private readonly QueryPlanner $planner,
        private readonly LlmClient $llm,
        private readonly PrincipalVacuum $vacuum,
    ) {}

    public function handle(User $user, string $question): array
    {
        if (! $user->hasRole('principal')) {
            return [
                'answer' => 'Access denied. VACUUM queries require the Principal role.',
                'records' => [],
            ];
        }

        // Real LLM answer when configured; fall back to the keyword planner otherwise.
        if ($this->llm->configured()) {
            try {
                return $this->vacuum->answer($question);
            } catch (\Throwable $e) {
                Log::warning('VACUUM LLM failed, falling back to planner', ['error' => $e->getMessage()]);
            }
        }

        return $this->planner->plan($question);
    }
}
