<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'students',
            'consents',
            'timetable_entries',
            'calendar_events',
        ];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'synced_time')) continue;
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('synced_time')->nullable()->after('updated_at');
            });
        }
    }

    public function down(): void
    {
        $tables = ['students', 'consents', 'timetable_entries', 'calendar_events'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'synced_time')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('synced_time');
                });
            }
        }
    }
};
