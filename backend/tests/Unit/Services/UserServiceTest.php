<?php

/**
 * UserServiceTest — screen-032--kelola-user-role / usecase-032--kelola-user-role.
 *
 * Unit tests for App\Services\UserService::listUsers()/::create()/
 * ::update()/::setStatus(), covering the unit_test_cases derived from
 * this screen's business_logic. Calls the service directly (no HTTP
 * layer), mirroring tests/Unit/Services/BusinessUnitServiceTest.php's
 * pragmatic RefreshDatabase + model-factory approach (no injectable
 * repository abstraction exists in this codebase).
 */

use App\Enums\UserRole;
use App\Exceptions\CannotDeactivateSelfException;
use App\Models\BusinessUnit;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new UserService();
    $this->businessUnit = BusinessUnit::factory()->create();
});

it('returns paginated user list with business_unit_name for each row', function () {
    User::factory()->count(3)->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();

    $result = $this->service->listUsers(1, 20);

    expect($result['data'])->toHaveCount(3);
    expect($result['data'][0])->toHaveKey('business_unit_name', $this->businessUnit->name);
    expect($result['data'][0])->not->toHaveKey('password_hash');
});

it('filters list by role and business_unit_id when provided', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
    User::factory()->role(UserRole::Supervisor)->forBusinessUnit($otherBusinessUnit)->create();

    $result = $this->service->listUsers(1, 20, role: 'supervisor', businessUnitId: $this->businessUnit->id);

    expect($result['data'])->toHaveCount(1);
});

it('throws a ValidationException when creating a user with a duplicate username', function () {
    User::factory()->create(['username' => 'existinguser']);

    expect(fn () => $this->service->create([
        'username' => 'existinguser',
        'name' => 'New User',
        'role' => 'supervisor',
        'business_unit_id' => $this->businessUnit->id,
        'password' => 'Passw0rd!',
    ]))->toThrow(ValidationException::class);
});

it('throws a ValidationException when role is not admin and business_unit_id is missing', function () {
    expect(fn () => $this->service->create([
        'username' => 'newuser',
        'name' => 'New User',
        'role' => 'operator',
        'password' => 'Passw0rd!',
    ]))->toThrow(ValidationException::class);
});

it('allows creating an Admin user without business_unit_id', function () {
    $user = $this->service->create([
        'username' => 'newadmin',
        'name' => 'New Admin',
        'role' => 'admin',
        'password' => 'Passw0rd!',
    ]);

    expect($user['business_unit_id'])->toBeNull();
    expect($user['role'])->toBe('admin');
});

it('throws a ValidationException when creating with an invalid business_unit_id', function () {
    expect(fn () => $this->service->create([
        'username' => 'newuser',
        'name' => 'New User',
        'role' => 'supervisor',
        'business_unit_id' => '00000000-0000-0000-0000-000000000000',
        'password' => 'Passw0rd!',
    ]))->toThrow(ValidationException::class);
});

it('throws a ValidationException when password is shorter than 6 characters', function () {
    expect(fn () => $this->service->create([
        'username' => 'newuser',
        'name' => 'New User',
        'role' => 'supervisor',
        'business_unit_id' => $this->businessUnit->id,
        'password' => '123',
    ]))->toThrow(ValidationException::class);
});

it('hashes the password before storing on create', function () {
    $this->service->create([
        'username' => 'newuser',
        'name' => 'New User',
        'role' => 'supervisor',
        'business_unit_id' => $this->businessUnit->id,
        'password' => 'Passw0rd!',
    ]);

    $stored = User::where('username', 'newuser')->first();

    expect($stored->password_hash)->not->toBe('Passw0rd!');
    expect(Hash::check('Passw0rd!', $stored->password_hash))->toBeTrue();
});

it('returns a ModelNotFoundException when updating a non-existent user id', function () {
    expect(fn () => $this->service->update('00000000-0000-0000-0000-000000000000', [
        'name' => 'Updated Name',
        'role' => 'supervisor',
        'business_unit_id' => $this->businessUnit->id,
    ]))->toThrow(ModelNotFoundException::class);
});

it('update() does not modify password_hash', function () {
    $user = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->password('OriginalPass1!')->create();
    $originalHash = $user->password_hash;

    $this->service->update($user->id, [
        'name' => 'Updated Name',
        'role' => 'supervisor',
        'business_unit_id' => $this->businessUnit->id,
    ]);

    expect($user->fresh()->password_hash)->toBe($originalHash);
});

it('throws a CannotDeactivateSelfException when the acting admin deactivates their own account', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    expect(fn () => $this->service->setStatus($admin->id, false, $admin->id))
        ->toThrow(CannotDeactivateSelfException::class);

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('allows an admin to deactivate a different user', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $other = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();

    $this->service->setStatus($other->id, false, $admin->id);

    expect($other->fresh()->is_active)->toBeFalse();
});

it('allows an admin to reactivate their own account (only deactivation of self is blocked)', function () {
    $admin = User::factory()->role(UserRole::Admin)->inactive()->create();

    $result = $this->service->setStatus($admin->id, true, $admin->id);

    expect($result['is_active'])->toBeTrue();
});
