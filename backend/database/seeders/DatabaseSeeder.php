<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(GradingParameterSeeder::class);
        $this->call(ThreshingOperationalTargetSeeder::class);
        $this->call(PressingOperationalTargetSeeder::class);
        $this->call(DepricarpingOperationalTargetSeeder::class);
        $this->call(KernelPlantOperationalTargetSeeder::class);
        $this->call(DemoAccountSeeder::class);
        $this->call(DemoMachineryDataSeeder::class);
        $this->call(DemoOperationalDataSeeder::class);
    }
}
