<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_decisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->string('academic_year');
            $table->float('yearly_average');
            $table->string('outcome');
            $table->string('ruleset_version');
            $table->string('pathway')->nullable();
            $table->timestamp('computed_at');
            $table->timestamps();
        });

        Schema::create('promotion_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('decision_id');
            $table->string('old_outcome');
            $table->string('new_outcome');
            $table->text('reason');
            $table->uuid('principal_id');
            $table->timestamp('overridden_at');
            $table->timestamps();
        });

        Schema::create('promotion_rulesets', function (Blueprint $table) {
            $table->string('version')->primary();
            $table->float('baseline')->default(10.0);
            $table->jsonb('coefficients')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_rulesets');
        Schema::dropIfExists('promotion_overrides');
        Schema::dropIfExists('promotion_decisions');
    }
};
