<?php

use App\Actions\Training\ManageTrainingCertificate;
use App\Models\Branch;
use App\Models\TrainingCertificate;
use App\Models\User;
use App\Notifications\Training\CertificateExpiringNotification;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/TrainingHelpers.php';

/*
| training:send-certificate-reminders — certificates expiring within the reminder
| window are reminded once (employee + training managers who can see the employee).
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 07:10:00'));
    seedTraining();
    Notification::fake();
    app(SettingsService::class)->set('training.certificate_reminder_days', 30);
    $this->employee = attendanceEmployee();
    $this->account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    $this->hr = adminWithRoles('HR Manager');
});

it('reminds the employee and the training managers once per certificate', function () {
    $certificate = TrainingCertificate::factory()->create(['employee_id' => $this->employee->id, 'expires_on' => '2026-12-01']);

    $this->artisan('training:send-certificate-reminders')->assertSuccessful();
    $this->artisan('training:send-certificate-reminders')->assertSuccessful();

    Notification::assertSentToTimes($this->account, CertificateExpiringNotification::class, 1);
    Notification::assertSentToTimes($this->hr, CertificateExpiringNotification::class, 1);
    expect($certificate->refresh()->reminder_sent_at)->not->toBeNull();
});

it('respects the window: not expired, not later than the reminder days', function () {
    TrainingCertificate::factory()->create(['employee_id' => $this->employee->id, 'expires_on' => '2026-12-13']);
    TrainingCertificate::factory()->create(['employee_id' => $this->employee->id, 'expires_on' => '2026-11-11']);
    TrainingCertificate::factory()->create(['employee_id' => $this->employee->id, 'expires_on' => null]);
    $edge = TrainingCertificate::factory()->create(['employee_id' => $this->employee->id, 'expires_on' => '2026-12-12']);

    $this->artisan('training:send-certificate-reminders')->assertSuccessful();

    Notification::assertSentToTimes($this->account, CertificateExpiringNotification::class, 1);
    Notification::assertSentTo($this->account, CertificateExpiringNotification::class, fn ($notification) => $notification->certificate->is($edge));
});

it('does not remind training managers outside the employee\'s data scope', function () {
    $scoped = adminWithRoles('HR Manager');
    $scoped->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => Branch::factory()->create()->id]);
    TrainingCertificate::factory()->create(['employee_id' => $this->employee->id, 'expires_on' => '2026-11-20']);

    $this->artisan('training:send-certificate-reminders')->assertSuccessful();

    Notification::assertNotSentTo($scoped, CertificateExpiringNotification::class);
    Notification::assertSentTo($this->hr, CertificateExpiringNotification::class);
});

it('re-arms the reminder when the expiry date changes', function () {
    $certificate = TrainingCertificate::factory()->create(['employee_id' => $this->employee->id, 'expires_on' => '2026-11-30']);
    $this->artisan('training:send-certificate-reminders');

    app(ManageTrainingCertificate::class)->update($certificate, [
        'type' => 'certificate', 'name' => $certificate->name, 'expires_on' => '2026-12-05',
    ], null, $this->hr);

    expect($certificate->refresh()->reminder_sent_at)->toBeNull();
});
