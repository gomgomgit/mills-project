<?php

namespace Database\Factories;

use App\Enums\Uom;
use App\Models\GradingDetail;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingDetail>
 *
 * Mirrors GradingRecordFactory.php's structure/conventions.
 */
class GradingDetailFactory extends Factory
{
    protected $model = GradingDetail::class;

    public function definition(): array
    {
        return [
            'grading_record_id' => GradingRecord::factory(),
            'grading_parameter_id' => GradingParameter::factory(),
            'quantity' => $this->faker->randomFloat(2, 1, 100),
            'uom' => $this->faker->randomElement(Uom::cases()),
            'percentage' => $this->faker->randomFloat(2, 0, 100),
        ];
    }

    public function forGradingRecord(GradingRecord|string $gradingRecord): self
    {
        return $this->state(fn () => [
            'grading_record_id' => $gradingRecord instanceof GradingRecord ? $gradingRecord->id : $gradingRecord,
        ]);
    }

    public function forGradingParameter(GradingParameter|string $gradingParameter): self
    {
        return $this->state(fn () => [
            'grading_parameter_id' => $gradingParameter instanceof GradingParameter ? $gradingParameter->id : $gradingParameter,
        ]);
    }
}
