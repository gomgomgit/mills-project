<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

dataset('admin equivalent roles', fn () => array_map(fn (UserRole $r) => [$r], UserRole::adminEquivalents()));

it('membaca role setara Admin sebagai Admin tetapi menyimpan role yang dipilih', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);
    $fresh = User::find($user->id);

    expect($fresh->role)->toBe(UserRole::Admin)
        ->and($fresh->assignedRole())->toBe($role)
        ->and($fresh->getRawOriginal('role'))->toBe($role->value);
})->with('admin equivalent roles');

it('menjaga role non-Admin tetap apa adanya', function () {
    foreach ([UserRole::Operator, UserRole::Supervisor, UserRole::MillManagement] as $role) {
        $user = User::find(User::factory()->create(['role' => $role])->id);
        expect($user->role)->toBe($role)->and($user->assignedRole())->toBe($role);
    }
});

it('mengarahkan role setara Admin ke /dashboard setelah login', function (UserRole $role) {
    $user = User::find(User::factory()->create(['role' => $role])->id);

    expect(app(AuthService::class)->redirectFor($user->role->value))->toBe('/dashboard');
})->with('admin equivalent roles');

it('lolos guard role:admin', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get('/users')->assertOk();
})->with('admin equivalent roles');
