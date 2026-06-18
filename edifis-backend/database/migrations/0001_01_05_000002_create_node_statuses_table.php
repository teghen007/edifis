<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('node_id');
            $table->timestamp('reported_at');
            $table->boolean('disk_ok')->default(true);
            $table->boolean('ups_on_battery')->default(false);
            $table->timestamp('last_sync_at')->nullable();
            $table->integer('pending_outbox')->default(0);
            $table->timestamps();

            $table->index(['node_id', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_statuses');
    }
};
