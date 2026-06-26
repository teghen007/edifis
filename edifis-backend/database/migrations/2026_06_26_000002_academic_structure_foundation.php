<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic structure foundation.
 *
 * Establishes ONE authoritative student->section link (students.stream_id) and
 * a class-scoped subject catalogue (class_subject) with class-specific codes
 * (e.g. "GEO 1" for Form 1 Geography, "GEO US" for Upper Sixth). Subjects are
 * assigned at the class level and cascade to that class's sections (streams).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->uuid('stream_id')->nullable()->after('current_class_id');
            $table->index('stream_id');
        });

        if (! Schema::hasTable('class_subject')) {
            Schema::create('class_subject', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('class_id');
                $table->uuid('subject_id');
                $table->string('code', 32);          // class-specific label, e.g. "GEO 1"
                $table->decimal('coefficient', 5, 2)->default(1);
                $table->timestamps();

                $table->unique(['class_id', 'subject_id']);
                $table->foreign('class_id')->references('id')->on('school_classes')->cascadeOnDelete();
                $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subject');
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['stream_id']);
            $table->dropColumn('stream_id');
        });
    }
};
