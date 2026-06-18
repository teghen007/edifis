<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_log', function (Blueprint $table) {
            $table->string('entity_id');
            $table->string('entity_revision');
            $table->timestamp('applied_at');
            $table->primary(['entity_id', 'entity_revision']);
        });

        Schema::create('audit_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id');
            $table->string('actor_role')->nullable();
            $table->string('action');
            $table->string('entity_type');
            $table->uuid('entity_id');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
        Schema::dropIfExists('idempotency_log');
    }
};
