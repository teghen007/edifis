<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('class_id');
            $table->uuid('subject_id');
            $table->uuid('teacher_id');
            $table->string('day_of_week');
            $table->string('period_start');
            $table->string('period_end');
            $table->string('room')->nullable();
            $table->uuid('created_by');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
        });

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('type');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('scope')->default('school');
            $table->uuid('class_id')->nullable();
            $table->uuid('created_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('timetable_entries');
    }
};
