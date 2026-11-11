<?php

namespace Database\Seeders;

use App\Actions\Attendance\ManageAttendanceDevice;
use App\Enums\DeviceSyncType;
use App\Enums\PunchDirection;
use App\Enums\WorkLocationScope;
use App\Models\AttendanceDevice;
use App\Models\AttendanceMobileAttempt;
use App\Models\AttendanceWorkLocationRule;
use App\Models\Branch;
use App\Models\BranchLocation;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\Devices\Data\DevicePunch;
use App\Services\Attendance\Devices\DeviceEventIngestor;
use App\Services\Attendance\Devices\DeviceMappingService;
use App\Services\Attendance\Devices\DeviceSyncService;
use App\Services\Attendance\Face\Data\FaceSample;
use App\Services\Attendance\Face\FaceEnrolmentService;
use App\Services\Attendance\Mobile\CheckInData;
use App\Services\Attendance\Mobile\MobileAttemptReview;
use App\Services\Attendance\Mobile\MobileCheckIn;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * Phase 5b demo data (never in production), through the real services:
 *
 * - three SIMULATED devices: CAI-SIM-01 (pull, most Cairo staff mapped),
 *   ALX-SIM-01 (push, a pushed batch + a re-sent duplicate batch) and
 *   GIZ-SIM-01 (pull with "simulate failure" on: failed syncs, backoff, health);
 * - unknown device users (unmatched events) and a file-import log;
 * - work-location rules (one remote employee, IT allowed at Cairo HQ too);
 * - face consents and SIMULATED enrolments (employee1, one field employee,
 *   one withdrawn = deleted), and mobile GPS attempts: accepted, rejected
 *   (outside geofence, poor accuracy, face retry) and flagged (mock location,
 *   not enrolled, time drift), one flagged attempt approved by the attendance officer.
 *
 * Device pulls start at today's midnight so they never duplicate the demo
 * punches of earlier days (AttendanceDemoSeeder).
 */
class AttendanceCaptureDemoSeeder extends Seeder
{
    public function run(ManageAttendanceDevice $devices, DeviceSyncService $sync, DeviceMappingService $mappings, DeviceEventIngestor $ingestor): void
    {
        if (AttendanceDevice::query()->withoutGlobalScopes()->exists()) {
            return;
        }

        $admin = User::query()->where('email', 'super.admin@hrmplus.test')->first();
        $officer = User::query()->where('email', 'attendance.officer@hrmplus.test')->first() ?? $admin;

        if ($admin === null) {
            return;
        }

        $branches = Branch::query()->withoutGlobalScopes()->get()->keyBy('code');
        $todayStart = CarbonImmutable::parse(AttendanceClock::date(now('Africa/Cairo'))->format('Y-m-d'), 'Africa/Cairo')->utc();

        // Cairo: pull device, most Cairo employees mapped (device user id = digits of the employee number).
        $cairo = $devices->create([
            'name' => 'Cairo HQ — main entrance', 'serial_number' => 'CAI-SIM-01', 'branch_id' => $branches['CAI']->id ?? null,
            'branch_location_id' => $this->locationId($branches['CAI'] ?? null), 'adapter' => 'simulated', 'connection_mode' => 'pull',
            'pull_interval_minutes' => 15, 'is_active' => true,
        ], $admin)['device'];
        $cairo->forceFill(['sync_cursor' => $todayStart->toIso8601ZuluString()])->save();
        $this->mapBranch($mappings, $cairo, $admin, except: 2);
        $sync->pull($cairo, $admin);

        // Unknown users on the Cairo device (yesterday, pushed by a bridge): left unmatched for the review screen.
        $sync->recordPush($cairo, [
            new DevicePunch('9001', $todayStart->subHours(16), PunchDirection::In, 'SIM-9001-a', 'fingerprint'),
            new DevicePunch('9001', $todayStart->subHours(7), PunchDirection::Out, 'SIM-9001-b', 'fingerprint'),
            new DevicePunch('9002', $todayStart->subHours(15), PunchDirection::In, 'SIM-9002-a', 'card'),
        ]);

        // Alexandria: push device with a batch and the same batch re-sent (duplicates).
        $alex = $devices->create([
            'name' => 'Alexandria — Smouha office', 'serial_number' => 'ALX-SIM-01', 'branch_id' => $branches['ALX']->id ?? null,
            'branch_location_id' => $this->locationId($branches['ALX'] ?? null), 'adapter' => 'simulated', 'connection_mode' => 'push', 'is_active' => true,
        ], $admin)['device'];
        $alexEmployees = $this->mapBranch($mappings, $alex, $admin, except: 0, limit: 6);
        $batch = [];

        foreach ($alexEmployees as $index => $employee) {
            $at = $todayStart->addHours(6)->addMinutes($index * 3 + 1);

            if ($at->isPast()) {
                $batch[] = new DevicePunch($this->deviceUserId($employee), $at, PunchDirection::In, 'ALX-'.$employee->id.'-'.$at->getTimestamp(), 'fingerprint');
            }
        }

        $batch[] = new DevicePunch('7777', $todayStart->subHours(17), PunchDirection::In, 'ALX-7777', 'fingerprint');
        $sync->recordPush($alex, $batch);
        $sync->recordPush($alex, $batch);

        // Giza: pull device with a simulated failure: two failed attempts, backoff, "needs attention".
        $giza = $devices->create([
            'name' => 'Smart Village — B12 lobby', 'serial_number' => 'GIZ-SIM-01', 'branch_id' => $branches['GIZ']->id ?? null,
            'branch_location_id' => $this->locationId($branches['GIZ'] ?? null), 'adapter' => 'simulated', 'connection_mode' => 'pull',
            'pull_interval_minutes' => 10, 'simulate_failure' => true, 'is_active' => true,
        ], $admin)['device'];
        $this->mapBranch($mappings, $giza, $admin, except: 0, limit: 5);
        $sync->test($giza, $admin);
        $sync->pull($giza);
        $sync->pull($giza);

        // A file import (CSV fallback) logged on the Cairo device.
        $log = $sync->start($cairo, DeviceSyncType::File, $officer, fileName: 'cai-sim-01-export.csv');
        $counts = $ingestor->ingest($cairo, [new DevicePunch('9003', $todayStart->subHours(40), PunchDirection::In, 'CSV-9003', 'file')], $log);
        $sync->succeed($cairo, $log, $counts, moveCursor: null, markSynced: false);

        $this->workLocations($admin);
        $this->face($admin);
        $this->mobileAttempts($officer);

        // Process what the device syncs and approvals queued (the demo does not need a queue worker).
        Artisan::call('attendance:process', ['--pending' => true]);
    }

    /**
     * @return list<Employee>
     */
    private function mapBranch(DeviceMappingService $mappings, AttendanceDevice $device, User $admin, int $except, int $limit = 100): array
    {
        $employees = Employee::query()->withoutGlobalScopes()->where('branch_id', $device->branch_id)->employed()
            ->whereDoesntHave('groups', fn ($query) => $query->where('code', 'FIELD'))
            ->orderBy('employee_number')->limit($limit + $except)->get();
        $mapped = [];

        foreach ($employees->slice($except) as $employee) {
            $mappings->map($device, $this->deviceUserId($employee), $employee, $admin, Str::limit($employee->name_en, 20, ''));
            $mapped[] = $employee;
        }

        return $mapped;
    }

    private function deviceUserId(Employee $employee): string
    {
        return (string) (int) preg_replace('/\D+/', '', $employee->employee_number);
    }

    private function locationId(?Branch $branch): ?int
    {
        return $branch === null ? null : BranchLocation::query()->withoutGlobalScopes()->where('branch_id', $branch->id)->orderBy('id')->value('id');
    }

    private function workLocations(User $admin): void
    {
        $field = Employee::query()->withoutGlobalScopes()->whereHas('groups', fn ($query) => $query->where('code', 'FIELD'))
            ->whereDoesntHave('user')->orderBy('employee_number')->first();

        if ($field !== null) {
            AttendanceWorkLocationRule::query()->create([
                'scope_type' => WorkLocationScope::Employee, 'scope_id' => $field->id, 'allow_anywhere' => true,
                'effective_from' => now()->startOfMonth()->toDateString(), 'effective_to' => now()->endOfMonth()->toDateString(),
                'note' => 'Demo: field visits this month', 'created_by' => $admin->id,
            ]);
        }

        $it = Department::query()->withoutGlobalScopes()->where('code', 'IT')->first();
        $branchIds = Branch::query()->withoutGlobalScopes()->whereIn('code', ['CAI', 'GIZ'])->pluck('id');

        if ($it !== null) {
            foreach (BranchLocation::query()->withoutGlobalScopes()->whereIn('branch_id', $branchIds)->get() as $location) {
                AttendanceWorkLocationRule::query()->create([
                    'scope_type' => WorkLocationScope::Department, 'scope_id' => $it->id, 'branch_location_id' => $location->id,
                    'note' => 'Demo: IT works from HQ and the tech centre', 'created_by' => $admin->id,
                ]);
            }
        }
    }

    private function face(User $admin): void
    {
        $faces = app(FaceEnrolmentService::class);

        foreach (['employee1@hrmplus.test'] as $email) {
            $user = User::query()->where('email', $email)->first();

            if ($user?->employee !== null) {
                $faces->giveConsent($user->employee, 'api', $user, '127.0.0.1');
                $faces->enrol($user->employee, new FaceSample('sim-enrol', livenessPassed: true), $user);
            }
        }

        $others = Employee::query()->withoutGlobalScopes()->whereHas('groups', fn ($query) => $query->where('code', 'FIELD'))
            ->whereDoesntHave('user')->orderBy('employee_number')->limit(2)->get();

        foreach ($others as $index => $employee) {
            $faces->giveConsent($employee, 'admin', $admin, null);
            $faces->enrol($employee, new FaceSample('sim-enrol', livenessPassed: true), $admin);

            if ($index === 1) {
                $faces->withdrawConsent($employee, $admin);
            }
        }
    }

    /**
     * Attempts at 08:05 today (or yesterday when it is still early), through MobileCheckIn.
     */
    private function mobileAttempts(User $officer): void
    {
        $local = now('Africa/Cairo');
        $moment = ($local->hour >= 9 ? $local : $local->subDay())->setTime(8, 5);
        $users = User::query()->whereIn('email', ['employee1@hrmplus.test', 'employee2@hrmplus.test', 'employee3@hrmplus.test'])->get()->keyBy('email');
        $checkIn = app(MobileCheckIn::class);
        $attempt = function (string $email, int $minutes, array $overrides) use ($users, $checkIn, $moment): ?AttendanceMobileAttempt {
            $user = $users->get($email);
            $employee = $user?->employee;
            $location = $employee ? BranchLocation::query()->withoutGlobalScopes()->where('branch_id', $employee->branch_id)->orderBy('id')->first() : null;

            if ($employee === null || $location === null) {
                return null;
            }

            Carbon::setTestNow($moment->copy()->addMinutes($minutes));
            $data = [
                'lat' => (float) $location->latitude + 0.0002, 'lng' => (float) $location->longitude + 0.0001, 'accuracy' => 12.0, 'mock' => false,
                'device_time' => CarbonImmutable::now(), 'face' => new FaceSample('sim-match', livenessPassed: true), 'direction' => PunchDirection::In,
                ...$overrides,
            ];
            $result = $checkIn->handle($employee, $user, new CheckInData(
                (string) Str::uuid(), $data['direction'], $data['lat'], $data['lng'], $data['accuracy'], $data['mock'], $data['device_time'],
                'demo-'.substr(md5($email), 0, 8), 'android', '1.0.0-demo', $data['face'], '127.0.0.1',
            ));
            Carbon::setTestNow();

            return $result->attempt;
        };

        // employee1: accepted (inside, face matched), then a face retry at check-out.
        $attempt('employee1@hrmplus.test', 0, []);
        $attempt('employee1@hrmplus.test', 480, ['direction' => PunchDirection::Out, 'face' => new FaceSample('sim-no-match')]);

        // employee3: outside the geofence (rejected), then a mock location (flagged), then time drift (flagged, approved).
        $attempt('employee3@hrmplus.test', 0, ['lat' => 31.30, 'lng' => 30.05]);
        $attempt('employee3@hrmplus.test', 2, ['mock' => true]);
        $drift = $attempt('employee3@hrmplus.test', 4, ['device_time' => CarbonImmutable::now()->subMinutes(25)]);

        // employee2: poor accuracy (rejected), then not enrolled (flagged for the manual fallback).
        $attempt('employee2@hrmplus.test', 0, ['accuracy' => 180.0]);
        $attempt('employee2@hrmplus.test', 3, []);

        if ($drift?->isPendingReview() && $officer->employee_id !== $drift->employee_id) {
            app(MobileAttemptReview::class)->approve($drift, $officer, 'Demo: phone clock was wrong after travel; location and face are fine.');
        }
    }
}
