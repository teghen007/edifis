<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Promotion\Models\PromotionDecision;
use App\Domain\Promotion\Models\PromotionOverride;

class OverridePromotion
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(string $decisionId, string $newOutcome, string $reason, string $principalId): PromotionOverride
    {
        $decision = PromotionDecision::findOrFail($decisionId);

        if ($decision->outcome === $newOutcome) {
            throw new \InvalidArgumentException('Override outcome must differ from the computed outcome.');
        }

        $override = PromotionOverride::create([
            'id' => (string) \Ramsey\Uuid\Uuid::uuid7(),
            'decision_id' => $decisionId,
            'old_outcome' => $decision->outcome,
            'new_outcome' => $newOutcome,
            'reason' => $reason,
            'principal_id' => $principalId,
            'overridden_at' => now(),
        ]);

        $this->audit->log(
            actorId: $principalId,
            action: 'promotion.override',
            entityType: 'promotion_decision',
            entityId: $decisionId,
            before: ['outcome' => $decision->outcome],
            after: ['outcome' => $newOutcome, 'reason' => $reason],
        );

        return $override;
    }
}
