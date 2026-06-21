<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->boolean('is_current')->default(false);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('streams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->uuid('class_id');
            $table->uuid('section_id');
            $table->uuid('academic_year_id');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('class_id')->references('id')->on('school_classes');
            $table->foreign('section_id')->references('id')->on('sections');
            $table->foreign('academic_year_id')->references('id')->on('academic_years');
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->uuid('academic_year_id');
            $table->integer('position');
            $table->timestamps();

            $table->foreign('academic_year_id')->references('id')->on('academic_years');
            $table->unique(['academic_year_id', 'position']);
        });

        Schema::create('tests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->uuid('term_id');
            $table->integer('position');
            $table->integer('default_max')->default(20);
            $table->timestamps();

            $table->foreign('term_id')->references('id')->on('terms');
            $table->unique(['term_id', 'position']);
        });

        Schema::create('subject_stream', function (Blueprint $table) {
            $table->uuid('subject_id');
            $table->uuid('stream_id');
            $table->timestamps();

            $table->foreign('subject_id')->references('id')->on('subjects');
            $table->foreign('stream_id')->references('id')->on('streams');
            $table->unique(['subject_id', 'stream_id']);
        });

        Schema::create('student_stream', function (Blueprint $table) {
            $table->uuid('student_id');
            $table->uuid('stream_id');
            $table->uuid('academic_year_id');
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students');
            $table->foreign('stream_id')->references('id')->on('streams');
            $table->foreign('academic_year_id')->references('id')->on('academic_years');
            $table->unique(['student_id', 'stream_id']);
        });

        Schema::create('student_subject', function (Blueprint $table) {
            $table->uuid('student_id');
            $table->uuid('subject_id');
            $table->uuid('stream_id');
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students');
            $table->foreign('subject_id')->references('id')->on('subjects');
            $table->foreign('stream_id')->references('id')->on('streams');
            $table->unique(['student_id', 'subject_id']);
        });

        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->uuid('teacher_id');
            $table->uuid('subject_id');
            $table->uuid('stream_id');
            $table->timestamps();

            $table->foreign('teacher_id')->references('id')->on('users');
            $table->foreign('subject_id')->references('id')->on('subjects');
            $table->foreign('stream_id')->references('id')->on('streams');
            $table->unique(['teacher_id', 'subject_id', 'stream_id']);
        });

        Schema::create('class_masters', function (Blueprint $table) {
            $table->uuid('teacher_id');
            $table->uuid('stream_id');
            $table->timestamps();

            $table->foreign('teacher_id')->references('id')->on('users');
            $table->foreign('stream_id')->references('id')->on('streams');
            $table->unique(['teacher_id', 'stream_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_masters');
        Schema::dropIfExists('teacher_assignments');
        Schema::dropIfExists('student_subject');
        Schema::dropIfExists('student_stream');
        Schema::dropIfExists('subject_stream');
        Schema::dropIfExists('tests');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('streams');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('academic_years');
    }
};
