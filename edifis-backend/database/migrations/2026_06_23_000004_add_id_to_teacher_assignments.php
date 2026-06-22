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
        if (Schema::hasColumn('teacher_assignments', 'id')) {
            return;
        }

        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->uuid('id')->nullable();
        });

        DB::statement('UPDATE teacher_assignments SET id = gen_random_uuid() WHERE id IS NULL');
        DB::statement('ALTER TABLE teacher_assignments ALTER COLUMN id SET NOT NULL');
        DB::statement('ALTER TABLE teacher_assignments ADD PRIMARY KEY (id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE teacher_assignments DROP CONSTRAINT IF EXISTS teacher_assignments_pkey');
        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->dropColumn('id');
        });
    }
};
