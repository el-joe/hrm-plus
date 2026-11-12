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
        // Phase 8a: approval workflows and reference data (rating scale, competencies,
        // asset categories, violation types) of performance, training, assets and disciplinary.
        $this->call(PerformanceDefaultsSeeder::class);
        $this->call(TrainingDefaultsSeeder::class);
        $this->call(AssetDefaultsSeeder::class);
        $this->call(DisciplinaryDefaultsSeeder::class);
        // Phase 8b: request categories, letter templates and the "hr_request" workflow; notification templates.
        $this->call(HrRequestDefaultsSeeder::class);
        $this->call(NotificationDefaultsSeeder::class);

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
            $this->call(PerformanceDemoSeeder::class);
            $this->call(TrainingDemoSeeder::class);
            $this->call(AssetDemoSeeder::class);
            $this->call(DisciplinaryDemoSeeder::class);
            // Devices + push first, so the request / announcement demo notifications also show simulated pushes.
            $this->call(NotificationDemoSeeder::class);
            $this->call(HrRequestDemoSeeder::class);
            $this->call(AnnouncementDemoSeeder::class);
        }
    }
}
