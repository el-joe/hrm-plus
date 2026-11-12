<?php

use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AttendanceCorrection;
use App\Models\DeviceToken;
use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryCase;
use App\Models\EmployeeContract;
use App\Models\EmployeeDocument;
use App\Models\EmployeeLoan;
use App\Models\HrRequest;
use App\Models\HrRequestAttachment;
use App\Models\JobApplication;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use App\Models\LeaveType;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use App\Models\Payslip;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\TrainingCertificate;
use App\Models\TrainingEnrolment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/*
| Ownership of the employee portal and of /api/v1/me/* (Phase 9, spec sections 5 and 9 "backend
| ownership checks"). The route list is read from the router, so a new portal / me route with an
| id parameter fails the first test until its parameter is registered below — and is then
| automatically probed by the second test with another employee's record: it must answer 404
| (never 403, never 2xx), for reads, writes and file downloads alike.
*/

/**
 * Route-name prefixes that must only ever reach the signed-in employee's own records.
 */
const OWNERSHIP_ROUTE_PREFIXES = ['portal.', 'api.v1.me.', 'api.v1.auth.sessions.'];

/**
 * Parameters that are not record ids (nothing to own).
 */
const OWNERSHIP_NON_ID_PARAMETERS = ['tab'];

/**
 * @return list<RouteDefinition>
 */
function ownershipRoutesWithIds(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => Str::startsWith((string) $route->getName(), OWNERSHIP_ROUTE_PREFIXES))
        // Signed-in routes only (the guest password-reset pages carry a {token} that is not a record).
        ->filter(fn (RouteDefinition $route): bool => array_intersect($route->gatherMiddleware(), ['portal', 'auth:sanctum']) !== [])
        ->filter(fn (RouteDefinition $route): bool => array_diff($route->parameterNames(), OWNERSHIP_NON_ID_PARAMETERS) !== [])
        ->values()->all();
}

/**
 * Another employee's record for each id parameter, created on first use. Attachments depend on the
 * parent route (leave request, HR request or announcement).
 *
 * @return array<string, Closure(RouteDefinition): (int|string)>
 */
function ownershipRecordMakers(): array
{
    $test = test();
    $other = fn () => $test->other;
    $otherUser = fn () => $test->otherUser;
    $foreign = [];
    $once = function (string $key, Closure $make) use (&$foreign): int|string {
        return $foreign[$key] ??= $make();
    };

    $leaveRequest = fn (): int => $once('leaveRequest', fn (): int => LeaveRequest::query()->forceCreate([
        'employee_id' => $other()->id, 'leave_type_id' => LeaveType::factory()->create()->id, 'start_date' => '2026-12-01',
        'end_date' => '2026-12-01', 'days' => '1.00', 'source' => 'portal', 'status' => 'pending',
    ])->id);
    $hrRequest = fn (): int => $once('hrRequest', fn (): int => HrRequest::factory()->create(['employee_id' => $other()->id])->id);
    $file = ['file_path' => 'x/other.pdf', 'file_name' => 'other.pdf', 'file_mime' => 'application/pdf', 'file_size' => 10];

    return [
        'document' => fn (): int => $once('document', fn (): int => EmployeeDocument::query()->forceCreate([
            'employee_id' => $other()->id, 'category' => 'other', 'title' => 'Theirs', 'visible_to_employee' => true, ...$file,
        ])->id),
        'contract' => fn (): int => $once('contract', fn (): int => EmployeeContract::query()->forceCreate([
            'employee_id' => $other()->id, 'contract_type' => 'fixed_term', 'start_date' => '2025-01-01', 'end_date' => '2027-01-01', ...$file,
        ])->id),
        'task' => fn (): int => $once('task', function () use ($other): int {
            $onboarding = Onboarding::query()->forceCreate(['employee_id' => $other()->id, 'start_date' => '2026-11-01', 'status' => 'in_progress']);

            return OnboardingTask::query()->forceCreate([
                'onboarding_id' => $onboarding->id, 'assignee' => 'employee', 'title_ar' => 'مهمة', 'title_en' => 'Task', 'status' => 'pending',
            ])->id;
        }),
        'correction' => fn (): int => $once('correction', fn (): int => AttendanceCorrection::query()->forceCreate([
            'employee_id' => $other()->id, 'work_date' => '2026-10-28', 'reason' => 'Forgot to punch', 'source' => 'portal',
        ])->id),
        'leaveRequest' => $leaveRequest,
        'hrRequest' => $hrRequest,
        'announcement' => fn (): int => $once('announcement', fn (): int => Announcement::factory()->create()->id),
        'attachment' => fn (RouteDefinition $route): int => match (true) {
            in_array('leaveRequest', $route->parameterNames(), true) => $once('leaveAttachment', fn (): int => LeaveRequestAttachment::query()->forceCreate([
                'leave_request_id' => $leaveRequest(), ...$file,
            ])->id),
            in_array('hrRequest', $route->parameterNames(), true) => $once('hrAttachment', fn (): int => HrRequestAttachment::query()->forceCreate([
                'hr_request_id' => $hrRequest(), 'is_internal' => false, 'is_letter' => true, ...$file,
            ])->id),
            default => $once('announcementAttachment', fn (): int => AnnouncementAttachment::query()->forceCreate([
                'announcement_id' => $once('announcement', fn (): int => Announcement::factory()->create()->id), ...$file,
            ])->id),
        },
        'loan' => fn (): int => $once('loan', fn (): int => EmployeeLoan::query()->forceCreate([
            'employee_id' => $other()->id, 'reference' => 'LN-OTHER-1', 'type' => 'loan', 'requested_amount' => '1000.00',
            'requested_instalments' => 2, 'requested_start' => '2026-12-01', 'source' => 'portal',
        ])->id),
        'payslip' => fn (): int => Payslip::query()->where('employee_id', $other()->id)->value('id'),
        'goal' => fn (): int => $once('goal', fn (): int => PerformanceGoal::factory()->create(['employee_id' => $other()->id])->id),
        'review' => fn (): int => $once('review', fn (): int => PerformanceReview::factory()->create(['employee_id' => $other()->id])->id),
        'enrolment' => fn (): int => $once('enrolment', fn (): int => TrainingEnrolment::factory()->create(['employee_id' => $other()->id])->id),
        'certificate' => fn (): int => $once('certificate', fn (): int => TrainingCertificate::factory()->create(['employee_id' => $other()->id, ...$file])->id),
        'assignment' => fn (): int => $once('assignment', fn (): int => AssetAssignment::query()->forceCreate([
            'asset_id' => Asset::factory()->create()->id, 'employee_id' => $other()->id, 'assigned_on' => '2026-10-01',
            'condition_on_issue' => 'good', 'status' => 'active',
        ])->id),
        'action' => fn (): int => $once('action', fn (): int => DisciplinaryAction::factory()->create([
            'disciplinary_case_id' => DisciplinaryCase::factory()->create(['employee_id' => $other()->id])->id,
            'employee_id' => $other()->id,
        ])->id),
        // Not someone else's employee record, but someone else's account data: notifications, push devices,
        // app tokens, web sessions, and interviews the user does not sit on.
        'notification' => fn (): string => $once('notification', fn (): string => $otherUser()->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['title' => 'Theirs', 'message' => 'Theirs'],
        ])->id),
        'device' => fn (): int => $once('device', function () use ($otherUser): int {
            $token = 'tok-'.bin2hex(random_bytes(20));

            return DeviceToken::query()->create([
                'user_id' => $otherUser()->id, 'token' => $token, 'token_hash' => DeviceToken::hashOf($token), 'platform' => 'android', 'last_seen_at' => now(),
            ])->id;
        }),
        'token' => fn (): int => $once('token', fn (): int => (int) $otherUser()->createToken('their-phone')->accessToken->id),
        'session' => fn (RouteDefinition $route): int|string => str_starts_with((string) $route->getName(), 'api.')
            ? $once('apiToken', fn (): int => (int) $otherUser()->createToken('their-phone')->accessToken->id)
            : $once('webSession', function () use ($otherUser): string {
                $id = Str::random(40);
                DB::table('sessions')->insert(['id' => $id, 'user_id' => $otherUser()->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'Other', 'payload' => '', 'last_activity' => now()->getTimestamp()]);

                return $id;
            }),
        'assignedInterview' => fn (): int => $once('interview', function (): int {
            seedRecruitment(); // job applications start in the default pipeline's first stage

            return JobApplication::factory()->create()->interviews()->create([
                'type' => 'hr', 'scheduled_at' => now()->addDay(), 'timezone' => 'UTC', 'mode' => 'phone', 'status' => 'scheduled',
            ])->id;
        }),
    ];
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00'));
    payrollSetup();

    $this->me = paidEmployee('6000.00');
    $this->other = paidEmployee('7000.00');
    $this->user = User::factory()->employee()->create(['employee_id' => $this->me->id]);
    $this->otherUser = User::factory()->employee()->create(['employee_id' => $this->other->id]);
    // Both have an issued payslip; the other one's is probed below.
    finalisedRun(calculatedRun(payrollPeriod('2026-10')));
});

it('knows how to build another employee\'s record for every id parameter of the portal and me/* routes', function () {
    $routes = ownershipRoutesWithIds();
    $parameters = collect($routes)->flatMap(fn (RouteDefinition $route): array => array_diff($route->parameterNames(), OWNERSHIP_NON_ID_PARAMETERS))->unique();

    expect($routes)->not->toBeEmpty()
        ->and($parameters->diff(array_keys(ownershipRecordMakers()))->values()->all())->toBe([]);
});

it('answers 404 for another employee\'s record on every portal and me/* route with an id', function () {
    $makers = ownershipRecordMakers();
    // Valid enough for every form so that validation can never answer before the ownership check.
    $payload = ['progress' => '50', 'note' => 'Note', 'body' => 'Hello there', 'reason' => 'A reason', 'response' => 'My response', 'comments' => 'Fine'];
    $probed = 0;

    foreach (ownershipRoutesWithIds() as $route) {
        $parameters = [];

        foreach (array_diff($route->parameterNames(), OWNERSHIP_NON_ID_PARAMETERS) as $name) {
            $parameters[$name] = $makers[$name]($route);
        }

        $method = collect($route->methods())->reject(fn (string $verb): bool => $verb === 'HEAD')->first();
        $url = route($route->getName(), $parameters);

        if (str_starts_with((string) $route->getName(), 'api.')) {
            Sanctum::actingAs($this->user, ['employee']);
            $response = $this->json($method, $url, $method === 'GET' ? [] : $payload);
        } else {
            $response = $this->actingAs($this->user, 'employee')->call($method, $url, $method === 'GET' ? [] : $payload);
        }

        expect($response->getStatusCode())->toBe(404, "{$method} {$route->uri()} ({$route->getName()}) answered {$response->getStatusCode()} for another employee's record");
        $probed++;
    }

    expect($probed)->toBeGreaterThan(40);
});

it('still serves the employee\'s own records on the same routes (the 404s are not blanket)', function () {
    $own = Payslip::query()->where('employee_id', $this->me->id)->value('id');

    $this->actingAs($this->user, 'employee')->get(route('portal.payroll.show', $own))->assertOk();
    Sanctum::actingAs($this->user, ['employee']);
    $this->getJson(route('api.v1.me.payslips.show', $own))->assertOk()->assertJsonPath('data.id', $own);
});

it('never lets an admin open an employee record through the portal by id', function () {
    // One account per employee: the admin account replaces the portal one.
    $this->user->forceFill(['employee_id' => null])->save();
    $admin = adminWithRoles('HR Manager');
    $admin->forceFill(['employee_id' => $this->me->id])->save();
    $theirs = Payslip::query()->where('employee_id', $this->other->id)->value('id');

    // An admin linked to an employee profile sees their own record only, whatever their permissions.
    $this->actingAs($admin, 'admin')->get(route('portal.payroll.show', $theirs))->assertNotFound();
    $this->actingAs($admin, 'admin')->get(route('portal.documents.contracts.download', ownershipForeignContract($this->other->id)))->assertNotFound();
});

it('refuses admin accounts on the employee API', function () {
    Sanctum::actingAs(adminWithRoles('HR Manager'), ['employee']);

    $this->getJson(route('api.v1.me.home'))->assertForbidden();
});

function ownershipForeignContract(int $employeeId): int
{
    return EmployeeContract::query()->forceCreate([
        'employee_id' => $employeeId, 'contract_type' => 'fixed_term', 'start_date' => '2025-01-01',
        'file_path' => 'x/c.pdf', 'file_name' => 'c.pdf', 'file_mime' => 'application/pdf', 'file_size' => 10,
    ])->id;
}
