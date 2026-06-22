<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Academics\Models\SchoolClass;
use App\Domain\Academics\Models\Subject;
use App\Domain\Students\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = -3;

    protected function getStats(): array
    {
        $students = Student::where('active', true)->count();
        $classes = SchoolClass::count();
        $subjects = Subject::count();

        // Net amount owed across the school (positive = owed to the school).
        $outstanding = (int) DB::table('ledger_entries')->sum('amount');
        $debtors = DB::table('ledger_entries')
            ->select('student_id')
            ->groupBy('student_id')
            ->havingRaw('SUM(amount) > 0')
            ->get()
            ->count();

        return [
            Stat::make('Active students', number_format($students))
                ->description('Enrolled this year')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make('Fees outstanding', number_format($outstanding) . ' XAF')
                ->description($debtors . ' students owing')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($outstanding > 0 ? 'danger' : 'success'),

            Stat::make('Classes', number_format($classes))
                ->description($subjects . ' subjects offered')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('info'),
        ];
    }
}
