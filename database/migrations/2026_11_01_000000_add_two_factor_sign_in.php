<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-factor sign-in with an authenticator app (App\Services\TwoFactor),
 * required for every account.
 *
 * users: the app's secret key and the recovery codes (both encrypted with
 * APP_KEY - a new APP_KEY means everyone sets it up again), when it was set
 * up (null = not yet, so the account is sent to set it up), and the last
 * 30-second step a code was used for, so one code can't be used twice.
 *
 * trusted_devices: "Trust this device for 30 days". The browser keeps a
 * random token in a cookie; only its hash is stored here, so a device can
 * be forgotten (password change, two-factor reset, Profile, term end).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->dateTime('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
        });

        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('label')->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']);
        });
    }
};
