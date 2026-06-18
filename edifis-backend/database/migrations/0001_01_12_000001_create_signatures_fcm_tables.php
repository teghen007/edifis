<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id');
            $table->uuid('staff_id');
            $table->text('image_data');
            $table->string('mime_type')->default('image/png');
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index('batch_id');
        });

        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token')->unique();
            $table->string('device_name')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fcm_tokens');
        Schema::dropIfExists('signatures');
    }
};
