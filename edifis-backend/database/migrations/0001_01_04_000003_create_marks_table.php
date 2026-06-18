<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('revision');
            $table->string('revision_parent')->nullable();
            $table->uuid('student_id');
            $table->uuid('subject_id');
            $table->uuid('class_id');
            $table->string('sequence');
            $table->uuid('owner_teacher_id');
            $table->float('score');
            $table->float('max_score');
            $table->float('coefficient')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('synced_time')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marks');
    }
};
