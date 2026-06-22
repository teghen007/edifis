<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedSmallInteger('coefficient')->default(1)->after('code');
        });

        // Preserve any coefficients already set per-stream: take the max per subject.
        DB::statement('
            UPDATE subjects s
            SET coefficient = sub.coeff
            FROM (
                SELECT subject_id, MAX(coefficient) AS coeff
                FROM subject_stream
                GROUP BY subject_id
            ) sub
            WHERE s.id = sub.subject_id
        ');
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('coefficient');
        });
    }
};
