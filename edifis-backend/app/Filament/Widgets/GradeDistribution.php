<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class GradeDistribution extends ChartWidget
{
    protected static ?string $heading = 'Grade distribution (latest term)';
    protected static ?int $sort = 1;
    protected static bool $isLazy = false;
    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRoleName(['principal', 'vice_principal', 'school_admin']) ?? false;
    }

    protected function getData(): array
    {
        $latest = DB::table('term_results')->orderByDesc('created_at')->value('term_id');
        $counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0];

        if ($latest) {
            $rows = DB::table('term_results')
                ->where('term_id', $latest)
                ->select('grade', DB::raw('count(*) as n'))
                ->groupBy('grade')
                ->pluck('n', 'grade');
            foreach ($counts as $g => $_) {
                $counts[$g] = (int) ($rows[$g] ?? 0);
            }
        }

        return [
            'datasets' => [[
                'label' => 'Students',
                'data' => array_values($counts),
                'backgroundColor' => ['#1E40AF', '#2563EB', '#3B82F6', '#60A5FA', '#93C5FD', '#CBD5E1'],
                'borderRadius' => 6,
            ]],
            'labels' => array_keys($counts),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
