<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\SchoolSettings;
use App\Filament\Resources\ConductRecordResource;
use App\Filament\Resources\FeeStructureResource;
use App\Filament\Resources\StaffUserResource;
use App\Filament\Resources\StudentResource;
use App\Filament\Resources\SubjectResource;
use App\Filament\Resources\TeacherAssignmentResource;
use Filament\Widgets\Widget;

class QuickLinks extends Widget
{
    protected static string $view = 'filament.widgets.quick-links';
    protected static ?int $sort = -2;
    protected int|string|array $columnSpan = 'full';

    /** @return array<int,array{label:string,icon:string,url:string}> */
    public function getLinks(): array
    {
        $candidates = [
            ['Students', 'heroicon-o-academic-cap', fn () => StudentResource::canAccess(), fn () => StudentResource::getUrl()],
            ['Staff & Roles', 'heroicon-o-users', fn () => StaffUserResource::canAccess(), fn () => StaffUserResource::getUrl()],
            ['Subjects & Coefficients', 'heroicon-o-book-open', fn () => SubjectResource::canAccess(), fn () => SubjectResource::getUrl()],
            ['Teacher Assignments', 'heroicon-o-clipboard-document-list', fn () => TeacherAssignmentResource::canAccess(), fn () => TeacherAssignmentResource::getUrl()],
            ['Fee Structures', 'heroicon-o-banknotes', fn () => FeeStructureResource::canAccess(), fn () => FeeStructureResource::getUrl()],
            ['Conduct', 'heroicon-o-shield-check', fn () => ConductRecordResource::canAccess(), fn () => ConductRecordResource::getUrl()],
            ['School Settings', 'heroicon-o-cog-6-tooth', fn () => SchoolSettings::canAccess(), fn () => SchoolSettings::getUrl()],
        ];

        $links = [];
        foreach ($candidates as [$label, $icon, $canFn, $urlFn]) {
            try {
                if ($canFn()) {
                    $links[] = ['label' => $label, 'icon' => $icon, 'url' => $urlFn()];
                }
            } catch (\Throwable $e) {
                // skip a link that can't resolve (e.g. resource hidden) — never break the dashboard
            }
        }

        return $links;
    }
}
