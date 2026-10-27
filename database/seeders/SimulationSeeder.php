<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class SimulationSeeder extends Seeder
{
    /**
     * Run the simulation seeder.
     */
    public function run(): void
    {
        Artisan::call('meshwar:simulate', [
            '--vehicles' => 300,
            '--bookings' => 50,
            '--requests' => 20,
        ]);
    }
}
