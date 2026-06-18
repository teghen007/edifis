<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mark_conflicts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('mark_id');
            $table->string('winning_revision');
            $table->string('rejected_revision');
            $table->uuid('resolved_by')->nullable();
            $table->timestamp('resolved_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mark_conflicts');
    }
};
