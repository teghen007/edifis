<?php

declare(strict_types=1);

namespace App\Domain\Sync\Jobs;

use App\Domain\Sync\Actions\ApplyEnvelope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ApplySyncBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        private readonly array $envelope,
    ) {}

    public function handle(ApplyEnvelope $apply): void
    {
        $apply->push($this->envelope);
    }
}
