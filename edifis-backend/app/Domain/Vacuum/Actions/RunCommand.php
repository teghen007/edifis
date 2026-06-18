<?php

declare(strict_types=1);

namespace App\Domain\Vacuum\Actions;

use App\Domain\Academics\Actions\RecordMark;
use App\Domain\Academics\Models\Mark;
use App\Domain\Auth\Actions\RevokeUser;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Promotion\Actions\OverridePromotion;
use App\Domain\Promotion\Models\PromotionDecision;
use App\Domain\Vacuum\Services\VacuumGuard;
use App\Models\User;

class RunCommand
{
    public function __construct(
        private readonly VacuumGuard $guard,
        private readonly AuditLogger $audit,
        private readonly RecordMark $recordMark,
        private readonly OverridePromotion $overridePromotion,
        private readonly RevokeUser $revokeUser,
    ) {}

    public function handle(User $principal, string $command, array $target, array $payload, string $reason, bool $confirm): array
    {
        $this->guard->requirePrincipal($principal);
        $this->guard->requireNonFinance($target['type'] ?? '');
        $this->guard->requireConfirm($command, $confirm);

        $before = $this->snapshot($target);
        $applied = null;

        switch ($command) {
            case 'correct_mark':
                $mark = Mark::findOrFail($target['mark_id']);
                $before = $mark->toArray();

                $applied = $this->recordMark->handle(
                    id: $target['mark_id'],
                    revision: (string) \Ramsey\Uuid\Uuid::uuid7(),
                    revisionParent: $payload['revision_parent'] ?? null,
                    studentId: $mark->student_id,
                    subjectId: $mark->subject_id,
                    classId: $mark->class_id,
                    sequence: $mark->sequence,
                    ownerTeacherId: $principal->id,
                    score: (float) ($payload['score'] ?? $mark->score),
                    maxScore: (float) ($payload['max_score'] ?? $mark->max_score),
                    coefficient: isset($payload['coefficient']) ? (float) $payload['coefficient'] : $mark->coefficient,
                    reason: $reason,
                );

                $after = $applied->toArray();
                break;

            case 'override_promotion':
                $decision = PromotionDecision::findOrFail($target['decision_id']);
                $before = $decision->toArray();

                $applied = $this->overridePromotion->handle(
                    decisionId: $target['decision_id'],
                    newOutcome: $payload['new_outcome'],
                    reason: $reason,
                    principalId: $principal->id,
                );

                $after = $decision->fresh()->toArray();
                break;

            case 'deactivate_account':
                $user = User::findOrFail($target['account_id']);
                $before = ['active' => $user->active, 'id' => $user->id, 'email' => $user->email];

                $this->revokeUser->handle($user, $reason);
                $applied = ['deactivated' => $target['account_id']];

                $after = ['active' => $user->fresh()->active, 'id' => $user->id];
                break;

            default:
                throw new \InvalidArgumentException("Unknown VACUUM command: {$command}");
        }

        $auditEntry = $this->audit->log(
            actorId: $principal->id,
            actorRole: 'principal',
            action: 'vacuum.' . $command,
            entityType: $target['type'] ?? 'unknown',
            entityId: $target['mark_id'] ?? $target['decision_id'] ?? $target['account_id'] ?? 'unknown',
            before: $before,
            after: $after ?? [],
        );

        return [
            'applied' => $applied,
            'audit' => [$auditEntry->toArray()],
        ];
    }

    private function snapshot(array $target): array
    {
        return ['target' => $target, 'at' => now()->toIso8601ZuluString()];
    }
}
