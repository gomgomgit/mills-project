<?php

/**
 * KelolaUserRoleTest (Feature/Api) — screen-032--kelola-user-role /
 * usecase-032--kelola-user-role.
 *
 * Integration tests for GET/POST /api/users, PATCH /api/users/{id}, and
 * PATCH /api/users/{id}/status (App\Http\Controllers\Api\UserController).
 * Exercises the real route -> 'auth:web' + 'role:admin' middleware ->
 * controller -> UserService -> Eloquent chain against the sqlite
 * in-memory testing DB. Mirrors tests/Feature/Api/KelolaBusinessUnitTest.php's
 * structure.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
});

it('berhasil: Admin creates a new user with role and business unit, returns 201', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/users', [
        'username' => 'supervisor02',
        'name' => 'Andi Wijaya',
        'role' => 'supervisor',
        'business_unit_id' => $this->businessUnit->id,
        'password' => 'Passw0rd!',
    ]);

    $response->assertCreated();
    $response->assertJsonFragment(['username' => 'supervisor02', 'role' => 'supervisor']);
    $response->assertJsonMissingPath('password_hash');
});

it('berhasil: Admin edits an existing user', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/users/{$this->supervisor->id}", [
        'name' => 'Andi Wijaya Updated',
        'role' => 'mill_management',
        'business_unit_id' => $this->businessUnit->id,
    ]);

    $response->assertOk();
    $response->assertJsonFragment(['name' => 'Andi Wijaya Updated', 'role' => 'mill_management']);
});

it('returns 422 VALIDATION_ERROR when creating with a duplicate username', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/users', [
        'username' => $this->supervisor->username,
        'name' => 'User Lain',
        'role' => 'supervisor',
        'business_unit_id' => $this->businessUnit->id,
        'password' => 'Passw0rd!',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('username');
});

it('returns 422 VALIDATION_ERROR when role is not admin and business_unit_id is missing', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/users', [
        'username' => 'operator05',
        'name' => 'Operator Baru',
        'role' => 'operator',
        'password' => 'Passw0rd!',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('business_unit_id');
});

it('returns 404 NOT_FOUND when updating a non-existent user id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson('/api/users/00000000-0000-0000-0000-000000000000', [
        'name' => 'Ghost',
        'role' => 'supervisor',
        'business_unit_id' => $this->businessUnit->id,
    ]);

    $response->assertStatus(404);
});

it('returns 409 CANNOT_DEACTIVATE_SELF when the admin deactivates their own account', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/users/{$this->admin->id}/status", [
        'is_active' => false,
    ]);

    $response->assertStatus(409);
    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('berhasil: Admin deactivates a different user', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/users/{$this->supervisor->id}/status", [
        'is_active' => false,
    ]);

    $response->assertOk();
    expect($this->supervisor->fresh()->is_active)->toBeFalse();
});

it('returns 403 FORBIDDEN for all endpoints when the authenticated user is not Admin', function () {
    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/users');

    $response->assertStatus(403);
});
