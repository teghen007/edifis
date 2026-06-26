<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OutstandingByClass extends ChartWidget
{
    protected static ?string $heading = 'Outstanding fees by class';
    protected static ?int $sort = 0;
    protected static bool $isLazy = false;
    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRoleName(['bursar', 'principal', 'vice_principal', 'school_admin']) ?? false;
    }

    protected function getData(): array
    {
        $rows = DB::table('ledger_entries')
            ->join('students', 'ledger_entries.student_id', '=', 'students.id')
            ->join('school_classes', 'students.current_class_id', '=', 'school_classes.id')
            ->select('school_classes.name', DB::raw('SUM(ledger_entries.amount) as bal'))
            ->groupBy('school_classes.name')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'amount' => max(0, (int) $r->bal)])
            ->filter(fn ($r) => $r['amount'] > 0)
            ->sortByDesc('amount')
            ->values();

        return [
            'datasets' => [[
                'label' => 'Outstanding (XAF)',
                'data' => $rows->pluck('amount')->all(),
                'backgroundColor' => '#2563EB',
                'borderRadius' => 6,
            ]],
            'labels' => $rows->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
