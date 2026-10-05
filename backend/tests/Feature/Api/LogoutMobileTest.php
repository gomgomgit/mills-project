<?php

/**
 * LogoutMobileTest (Feature/Api) — POST /api/logout (audit 2026-10-05).
 *
 * Temuan keamanan: aplikasi mobile memanggil POST /api/logout saat Logout,
 * tetapi route-nya tidak ada (404, ditelan best-effort di authStore.logout())
 * — token Sanctum perangkat TIDAK PERNAH dicabut dan tetap bisa dipakai
 * ulang setelah logout. Route sekarang mencabut HANYA token yang dipakai
 * request itu (currentAccessToken()); token perangkat lain milik user yang
 * sama tidak tersentuh. Request bersesi (web/SPA stateful) mengeluarkan
 * sesinya.
 *
 * Token asli (bukan Sanctum::actingAs) diterbitkan lewat POST /api/login
 * agar rantai route → guard sanctum → PersonalAccessToken teruji apa adanya.
 * forgetGuards() di antara request: guard meng-cache user dalam satu
 * instance aplikasi test, jadi tanpa itu request kedua tidak benar-benar
 * mengautentikasi ulang token-nya.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->user = User::factory()
        ->role(UserRole::Operator)
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->create(['username' => 'operator-logout']);
});

function issueMobileToken(object $test, string $device): string
{
    $token = $test->postJson('/api/login', [
        'username' => 'operator-logout',
        'password' => 'Passw0rd!',
        'device_name' => $device,
    ])->assertOk()->json('token');

    app('auth')->forgetGuards();

    return $token;
}

it('revokes the current token so replaying it afterwards is 401', function () {
    $token = issueMobileToken($this, 'HP Operator');

    $this->withToken($token)->getJson('/api/grading-parameters')->assertOk();
    app('auth')->forgetGuards();

    $this->withToken($token)->postJson('/api/logout')
        ->assertOk()
        ->assertJson(['message' => 'Logout berhasil.']);
    app('auth')->forgetGuards();

    expect(PersonalAccessToken::findToken($token))->toBeNull();
    $this->withToken($token)->getJson('/api/grading-parameters')->assertUnauthorized();
});

it('leaves the same user\'s other device tokens untouched', function () {
    $tokenA = issueMobileToken($this, 'HP A');
    $tokenB = issueMobileToken($this, 'HP B');

    $this->withToken($tokenA)->postJson('/api/logout')->assertOk();
    app('auth')->forgetGuards();

    expect(PersonalAccessToken::findToken($tokenA))->toBeNull();
    expect(PersonalAccessToken::findToken($tokenB))->not->toBeNull();
    $this->withToken($tokenB)->getJson('/api/grading-parameters')->assertOk();
});

it('returns 401 JSON for an unauthenticated logout', function () {
    $this->postJson('/api/logout')
        ->assertUnauthorized()
        ->assertJsonStructure(['message']);
});

it('returns 401 for an already-revoked token (double logout)', function () {
    $token = issueMobileToken($this, 'HP Operator');

    $this->withToken($token)->postJson('/api/logout')->assertOk();
    app('auth')->forgetGuards();

    $this->withToken($token)->postJson('/api/logout')->assertUnauthorized();
});

it('logs out the web session when the request is session-authenticated', function () {
    $this->actingAs($this->user, 'web')
        ->postJson('/api/logout')
        ->assertOk();

    $this->assertGuest('web');
    expect(PersonalAccessToken::count())->toBe(0);
});
