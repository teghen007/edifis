<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OutstandingByClass extends ChartWidget
{
    protected static ?string $heading = 'Top outstanding balances';
    protected static ?int $sort = 0;
    protected static bool $isLazy = false;
    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRoleName(['bursar', 'principal', 'vice_principal', 'school_admin']) ?? false;
    }

    protected function getData(): array
    {
        $balances = DB::table('ledger_entries')
            ->select('student_id', DB::raw('SUM(amount) as bal'))
            ->groupBy('student_id')
            ->get()
            ->filter(fn ($b) => (int) $b->bal > 0)
            ->sortByDesc('bal')
            ->take(8);

        $labels = [];
        $data = [];
        foreach ($balances as $b) {
            $s = DB::table('students')->where('id', $b->student_id)->first();
            $labels[] = trim(($s->given_name ?? '') . ' ' . ($s->family_name ?? ''));
            $data[] = (int) $b->bal;
        }

        return [
            'datasets' => [[
                'label' => 'Owed (XAF)',
                'data' => $data,
                'backgroundColor' => '#2563EB',
                'borderRadius' => 6,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
