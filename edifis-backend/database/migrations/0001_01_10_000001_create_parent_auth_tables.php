<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash')->nullable()->after('password');
            $table->boolean('must_reset_credential')->default(false)->after('pin_hash');
            $table->string('phone')->nullable()->unique()->after('email');
            $table->integer('login_attempts')->default(0)->after('must_reset_credential');
            $table->timestamp('locked_until')->nullable()->after('login_attempts');
        });

        Schema::create('login_otps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->integer('attempts')->default(0);
            $table->boolean('used')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'expires_at']);
        });

        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('device_token')->unique();
            $table->string('device_name')->nullable();
            $table->timestamp('trusted_until');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('device_token');
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('endpoint');
            $table->text('p256dh');
            $table->text('auth');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'endpoint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('trusted_devices');
        Schema::dropIfExists('login_otps');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['locked_until', 'login_attempts', 'phone', 'must_reset_credential', 'pin_hash']);
        });
    }
};
