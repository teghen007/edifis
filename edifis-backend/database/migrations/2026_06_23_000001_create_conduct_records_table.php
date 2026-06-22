<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conduct_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('term_id');
            $table->uuid('stream_id')->nullable();
            $table->string('conduct_grade');           // Excellent | Good | Fair | Poor
            $table->string('punctuality')->nullable();
            $table->text('comment')->nullable();
            $table->uuid('recorded_by')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'term_id']);
            $table->index(['stream_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conduct_records');
    }
};
