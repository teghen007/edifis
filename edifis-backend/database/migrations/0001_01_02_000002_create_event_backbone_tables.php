<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('cost')->comment('CFA minor units');
            $table->string('category');
            $table->jsonb('default_for_forms')->nullable();
            $table->string('isbn')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('issue_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('revision');
            $table->uuid('student_id');
            $table->uuid('catalogue_item_id');
            $table->bigInteger('cost')->comment('CFA minor units');
            $table->timestamp('issued_at');
            $table->timestamp('synced_time')->nullable();
            $table->uuid('staff_id');
            $table->string('signature_ref')->nullable();
            $table->uuid('batch_id');
            $table->string('device_id')->nullable();
            $table->string('status');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'revision']);
            $table->index('student_id');
            $table->index('status');
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('source_event_id');
            $table->bigInteger('amount');
            $table->timestamp('posted_at');
            $table->timestamp('synced_time')->nullable();
            $table->timestamps();

            $table->index('student_id');
            $table->index('source_event_id');
        });

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('class_id');
            $table->uuid('subject_id');
            $table->uuid('teacher_id')->nullable();
            $table->string('period');
            $table->integer('scanned_count')->default(0);
            $table->integer('headcount')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('revision');
            $table->uuid('session_id');
            $table->uuid('student_id');
            $table->timestamp('scanned_at');
            $table->timestamp('synced_time')->nullable();
            $table->string('device_id')->nullable();
            $table->uuid('scanned_by')->nullable();
            $table->string('source')->default('qr_scan');
            $table->string('status')->default('present');
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'student_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('issue_events');
        Schema::dropIfExists('catalogue_items');
    }
};
