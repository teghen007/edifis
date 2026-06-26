<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daily roll-call attendance.
 *
 * Attendance is taken per SECTION (stream) per DAY (optionally AM/PM) and has
 * nothing to do with a subject. A teacher selects a section, pulls the class
 * list, marks each student present/absent/late/excused with an optional reason,
 * and submits. The existing scan/QR flow is retained as an alternative `mode`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->uuid('subject_id')->nullable()->change();   // roll call is subject-free
            $table->uuid('stream_id')->nullable()->after('class_id');
            $table->date('attendance_date')->nullable()->after('stream_id');
            $table->string('mode', 16)->default('scan')->after('period'); // scan | rollcall
            $table->index(['stream_id', 'attendance_date', 'period']);
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->string('reason')->nullable()->after('status');
        });

        // Backfill a date for existing sessions so reporting stays sane.
        DB::statement("UPDATE attendance_sessions SET attendance_date = opened_at::date WHERE attendance_date IS NULL AND opened_at IS NOT NULL");
    }

    public function down(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex(['stream_id', 'attendance_date', 'period']);
            $table->dropColumn(['stream_id', 'attendance_date', 'mode']);
        });
    }
};
