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
            $table->string('ack_id')->nullable()->after('pulled_at');
        });

        Schema::table('revocations', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('mark_conflicts', function (Blueprint $table) {
            $table->dropColumn('ack_id');
        });

        Schema::table('revocations', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
