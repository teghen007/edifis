<?php

namespace App\Filament\Pages;

use App\Domain\Academics\Services\SeasonService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AcademicSeason extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Classes & Streams';
    protected static ?int $navigationSort = 0;
    protected static string $view = 'filament.pages.academic-season';

    public array $season = [];
    public array $years = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRoleName(['principal', 'vice_principal', 'school_admin']) ?? false;
    }

    public function getTitle(): string
    {
        return 'Academic Season';
    }

    public function mount(): void
    {
        $this->refreshSeason();
    }

    public function refreshSeason(): void
    {
        $service = app(SeasonService::class);
        $this->season = $service->current();
        $this->years = $service->years();
    }

    /** Only the principal / school admin drives the rotation. */
    public function canManageSeason(): bool
    {
        return auth()->user()?->hasAnyRoleName(['principal', 'school_admin']) ?? false;
    }

    public function openNextSequence(): void
    {
        $this->run(fn (SeasonService $s) => $s->openNextSequence(), 'Opened the next sequence.');
    }

    public function advanceTerm(): void
    {
        $this->run(fn (SeasonService $s) => $s->advanceTerm(), 'Term closed — results computed and the next term opened.');
    }

    public function reopenTerm(string $termId): void
    {
        $this->run(fn (SeasonService $s) => $s->reopenTerm($termId), 'Term reopened for late entries.');
    }

    public function closeYear(): void
    {
        $this->run(fn (SeasonService $s) => $s->closeYear(), 'Academic year ended — promotions deliberated and the new year opened.');
    }

    private function run(callable $fn, string $success): void
    {
        if (! $this->canManageSeason()) {
            Notification::make()->title('Only the principal can manage the season.')->danger()->send();

            return;
        }

        try {
            $fn(app(SeasonService::class));
            $this->refreshSeason();
            Notification::make()->title($success)->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Action blocked')->body($e->getMessage())->danger()->send();
        }
    }
}
