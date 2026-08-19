<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User — app's auth user model. Replaces Laravel's default User model.
 *
 * Roles: operator, supervisor, mill_management, admin.
 * Operators use the offline-first mobile app; Supervisor/Mill Management/Admin
 * primarily use the web app (Livewire), with Supervisor also on mobile.
 *
 * Note: this project's naming convention stores the password hash in the
 * `password_hash` column (not Laravel's conventional `password` column), so
 * getAuthPassword()/getAuthPasswordName() are overridden accordingly.
 *
 * Constraint: username harus unik (enforced by unique index on `users.username`).
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids;

    protected $fillable = [
        'username',
        'password_hash',
        'name',
        'role',
        'business_unit_id',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'role' => UserRole::class,
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The name of the column that stores the auth password.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * The hashed password for the auth user (kept in sync with getAuthPasswordName()
     * for code paths that call getAuthPassword() directly).
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }
}
