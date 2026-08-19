<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * personal_access_tokens — required by Laravel Sanctum's HasApiTokens trait
 * (App\Models\User) for screen-002--login-mobile / usecase-002--login-mobile
 * to issue tokens via $user->createToken().
 *
 * Not auto-loaded by Sanctum's service provider (it only offers this table
 * via `php artisan vendor:publish --tag=sanctum-migrations`, which had not
 * been run yet), so it is added here as a first-class project migration —
 * otherwise AuthService::login()'s mobile branch would fail at runtime with
 * "table 'personal_access_tokens' doesn't exist" the first time a
 * device_name login is attempted.
 *
 * Deviation from Sanctum's stock migration: uses uuidMorphs() instead of
 * morphs() for the `tokenable` polymorphic columns, because App\Models\User
 * (the only tokenable model in this app) uses HasUuids — a bigIncrements-
 * typed `tokenable_id` (Sanctum's default) would silently never match
 * User's UUID `id`, breaking Auth::guard('sanctum')'s token → user lookup.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
