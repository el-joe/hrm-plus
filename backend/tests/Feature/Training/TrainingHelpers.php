<?php

/*
| Helpers of the training tests (Phase 8a). Required by each training test file;
| guarded so several files can include them in one run.
*/

use App\Actions\Training\ManageTrainingEnrolment;
use App\Actions\Training\ManageTrainingSession;
use App\Actions\Training\RecordTrainingAttendance;
use App\Models\Employee;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrolment;
use App\Models\TrainingSession;
use App\Models\User;
use Database\Seeders\TrainingDefaultsSeeder;

if (! function_exists('seedTraining')) {
    /**
     * Roles, permissions and the "training_enrolment" approval workflow.
     */
    function seedTraining(): void
    {
        seedRoles();
        test()->seed(TrainingDefaultsSeeder::class);
    }

    /**
     * A scheduled session created through the action (default: two days, 10 seats, 1000.00 per seat, in two weeks).
     *
     * @param  array<string, mixed>  $attributes
     */
    function trainingSession(array $attributes = [], ?TrainingCourse $course = null, ?User $by = null): TrainingSession
    {
        $course ??= TrainingCourse::factory()->create();
        $start = now()->addDays(14)->toDateString();

        return app(ManageTrainingSession::class)->create([
            'training_course_id' => $course->id, 'start_date' => $start, 'end_date' => now()->addDays(15)->toDateString(),
            'delivery_mode' => 'classroom', 'location' => 'Room A', 'capacity' => 10, ...$attributes,
        ], $by ?? adminWithRoles('HR Manager'));
    }

    /**
     * An HR nomination (enrolled at once).
     */
    function enrolEmployee(TrainingSession $session, Employee $employee, ?User $hr = null): TrainingEnrolment
    {
        return app(ManageTrainingEnrolment::class)->nominate($session, $employee, $hr ?? adminWithRoles('HR Manager'));
    }

    /**
     * Record one attendance status for an enrolment on every given day (Y-m-d), as of that day's evening.
     *
     * @param  list<string>  $days
     */
    function attendTraining(TrainingEnrolment $enrolment, array $days, string $status = 'present', ?User $by = null): void
    {
        $by ??= adminWithRoles('HR Manager');

        foreach ($days as $day) {
            test()->travelTo(now()->parse($day.' 17:00:00'));
            app(RecordTrainingAttendance::class)->handle($enrolment->session, $day, [$enrolment->id => ['status' => $status]], $by);
        }
    }
}
