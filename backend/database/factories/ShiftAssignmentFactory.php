<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftAssignment>
 *
 * screen-171--jadwal-shift test infrastructure.
 *
 * work_date ditulis sebagai string 'YYYY-MM-DD' (bukan Carbon) supaya yang
 * tersimpan sama di SQLite dan PostgreSQL. Tanggal default TETAP, bukan
 * now(): factory yang bergantung pada hari ini membuat tes berubah makna
 * dari hari ke hari.
 */
class ShiftAssignmentFactory extends Factory
{
    protected $model = ShiftAssignment::class;

    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'user_id' => User::factory(),
            'work_date' => '2026-10-05',
            'created_by' => User::factory(),
        ];
    }

    public function onDate(string $date): static
    {
        return $this->state(fn () => ['work_date' => $date]);
    }
}
