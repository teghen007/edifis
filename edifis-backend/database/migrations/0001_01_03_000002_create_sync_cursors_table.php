<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_cursors', function (Blueprint $table) {
            $table->string('node_id');
            $table->string('entity_type');
            $table->string('cursor')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->primary(['node_id', 'entity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_cursors');
    }
};
