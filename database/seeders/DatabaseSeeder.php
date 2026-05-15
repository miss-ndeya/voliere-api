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
        // Appeler le seeder de démonstration
        $this->call([
            DemoSeeder::class,
        ]);
    }
}
