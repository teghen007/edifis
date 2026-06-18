<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mark_conflicts', function (Blueprint $table) {
            $table->timestamp('pulled_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('mark_conflicts', function (Blueprint $table) {
            $table->dropColumn('pulled_at');
        });
    }
};
