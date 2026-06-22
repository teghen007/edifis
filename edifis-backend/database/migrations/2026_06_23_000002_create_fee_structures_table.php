<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('class_id');
            $table->string('name');                       // e.g. Tuition, PTA, Boarding
            $table->bigInteger('amount');                 // XAF
            $table->string('applies_to')->default('all'); // all | day | boarding
            $table->uuid('academic_year_id')->nullable();
            $table->timestamps();

            $table->index('class_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('boarding_status')->default('day')->after('active'); // day | boarding
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->string('description')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('boarding_status');
        });
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
