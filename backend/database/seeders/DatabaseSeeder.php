<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (! app()->isProduction()) {
            $this->call(OrganisationStructureSeeder::class);
            $this->call(EmployeeSeeder::class);
            $this->call(DevelopmentAccountsSeeder::class);
        }
    }
}
