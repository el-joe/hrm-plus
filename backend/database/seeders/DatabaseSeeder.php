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
        $this->call(RecruitmentDefaultsSeeder::class);
        $this->call(AttendanceDefaultsSeeder::class);
        $this->call(LeaveDefaultsSeeder::class);
        $this->call(PayrollDefaultsSeeder::class);

        if (! app()->isProduction()) {
            $this->call(OrganisationStructureSeeder::class);
            $this->call(EmployeeSeeder::class);
            $this->call(DevelopmentAccountsSeeder::class);
            $this->call(RecruitmentDemoSeeder::class);
            $this->call(AttendanceDemoSeeder::class);
            $this->call(AttendanceCaptureDemoSeeder::class);
            $this->call(LeaveDemoSeeder::class);
            $this->call(PayrollDemoSeeder::class);
            $this->call(PayrollRunDemoSeeder::class);
        }
    }
}
