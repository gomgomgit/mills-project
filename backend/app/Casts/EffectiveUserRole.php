<?php

namespace App\Casts;

use App\Enums\UserRole;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast users.role: dibaca sebagai role EFEKTIF (role setara Admin → Admin),
 * ditulis sebagai role yang sebenarnya dipilih. Role yang dipilih tetap
 * terbaca lewat User::assignedRole().
 */
class EffectiveUserRole implements CastsAttributes
{
    /**
     * Tanpa cache objek: tanpa ini model yang baru dibuat mengembalikan enum
     * yang diberikan ke set() (mis. Engineer), bukan role efektifnya.
     */
    public bool $withoutObjectCaching = true;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?UserRole
    {
        return $value === null ? null : UserRole::from($value)->effective();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof UserRole ? $value->value : $value;
    }
}
