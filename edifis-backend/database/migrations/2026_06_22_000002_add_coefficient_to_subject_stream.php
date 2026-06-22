<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_stream', function (Blueprint $table) {
            $table->unsignedSmallInteger('coefficient')->default(1)->after('stream_id');
        });
    }

    public function down(): void
    {
        Schema::table('subject_stream', function (Blueprint $table) {
            $table->dropColumn('coefficient');
        });
    }
};
