<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The boarders (dorm) roll call has no single class, so attendance_sessions.class_id
 * must be nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->uuid('class_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->uuid('class_id')->nullable(false)->change();
        });
    }
};
