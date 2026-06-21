<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('grade');
            $table->float('point');
            $table->float('min_score');
            $table->float('max_score');
            $table->string('remark');
            $table->timestamps();
        });

        Schema::create('subject_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('subject_id');
            $table->uuid('stream_id');
            $table->uuid('term_id');
            $table->float('average');
            $table->string('grade');
            $table->float('point');
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students');
            $table->foreign('subject_id')->references('id')->on('subjects');
            $table->foreign('stream_id')->references('id')->on('streams');
            $table->foreign('term_id')->references('id')->on('terms');
            $table->unique(['student_id', 'subject_id', 'term_id']);
        });

        Schema::create('term_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('stream_id');
            $table->uuid('term_id');
            $table->float('overall_average');
            $table->string('grade');
            $table->float('total_points');
            $table->integer('position');
            $table->integer('subjects_count');
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students');
            $table->foreign('stream_id')->references('id')->on('streams');
            $table->foreign('term_id')->references('id')->on('terms');
            $table->unique(['student_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_results');
        Schema::dropIfExists('subject_results');
        Schema::dropIfExists('grade_rules');
    }
};
