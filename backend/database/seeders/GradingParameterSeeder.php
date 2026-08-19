<?php

namespace Database\Seeders;

use App\Enums\Uom;
use App\Models\GradingParameter;
use Illuminate\Database\Seeder;

/**
 * Seeds the 16 canonical Grading Parameter rows — entity-catalog:
 * grading-parameter constraint "16 baris kanonis harus di-seed: 13 ber-uom
 * bunch, 3 ber-uom kg". Idempotent via firstOrCreate() keyed on `name`
 * (unique), so re-running does not duplicate rows.
 */
class GradingParameterSeeder extends Seeder
{
    public function run(): void
    {
        $parameters = [
            ['name' => 'Mentah', 'uom' => Uom::Bunch],
            ['name' => 'Mengkal / Kurang Masak', 'uom' => Uom::Bunch],
            ['name' => 'Masak', 'uom' => Uom::Bunch],
            ['name' => 'Over ripe R-1', 'uom' => Uom::Bunch],
            ['name' => 'Over ripe R-2', 'uom' => Uom::Bunch],
            ['name' => 'Over ripe R->2', 'uom' => Uom::Bunch],
            ['name' => 'Janjang Kosong', 'uom' => Uom::Bunch],
            ['name' => 'Parthenocarpic / Abnormal', 'uom' => Uom::Bunch],
            ['name' => 'Buah Sakit', 'uom' => Uom::Bunch],
            ['name' => 'Hard bunch', 'uom' => Uom::Bunch],
            ['name' => 'Buah Masak Tangkai Panjang', 'uom' => Uom::Bunch],
            ['name' => 'Buah kecil / < 3.5 kg', 'uom' => Uom::Bunch],
            ['name' => 'Dimakan Hama / Tikus / Lainnya', 'uom' => Uom::Bunch],
            ['name' => 'Brondolan Segar', 'uom' => Uom::Kg],
            ['name' => 'Brondolan Busuk', 'uom' => Uom::Kg],
            ['name' => 'Brondolan Sampah', 'uom' => Uom::Kg],
        ];

        foreach ($parameters as $index => $parameter) {
            GradingParameter::firstOrCreate(
                ['name' => $parameter['name']],
                [
                    'uom' => $parameter['uom'],
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
