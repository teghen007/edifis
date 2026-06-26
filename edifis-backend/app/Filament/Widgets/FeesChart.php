<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class FeesChart extends ChartWidget
{
    protected static ?string $heading = 'Fees — collected vs outstanding';
    protected static ?int $sort = -1;
    protected static bool $isLazy = false;
    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRoleName(['bursar', 'principal', 'vice_principal', 'school_admin']) ?? false;
    }

    protected function getData(): array
    {
        $collected = abs((int) DB::table('ledger_entries')->where('amount', '<', 0)->sum('amount'));

        $outstanding = 0;
        foreach (DB::table('ledger_entries')->select('student_id', DB::raw('SUM(amount) as bal'))->groupBy('student_id')->get() as $b) {
            if ((int) $b->bal > 0) {
                $outstanding += (int) $b->bal;
            }
        }

        return [
            'datasets' => [[
                'data' => [$collected, $outstanding],
                'backgroundColor' => ['#38BDF8', '#1E40AF'], // sky vs deep wisdom blue
                'borderWidth' => 0,
            ]],
            'labels' => ['Collected (XAF)', 'Outstanding (XAF)'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
