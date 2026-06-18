<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('master_pea_id')->nullable()->unique();
            $table->string('given_name');
            $table->string('family_name');
            $table->string('other_names')->nullable();
            $table->string('sex', 1)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->uuid('current_class_id');
            $table->string('photo_ref')->nullable();
            $table->timestamp('enrolled_at');
            $table->string('demographics_revision')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->string('consenter_name');
            $table->string('relationship');
            $table->string('consenter_contact')->nullable();
            $table->timestamp('consented_at');
            $table->jsonb('scope');
            $table->integer('version')->default(1);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
        Schema::dropIfExists('students');
    }
};
