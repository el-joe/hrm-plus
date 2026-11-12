<?php

namespace App\Services\Training;

use App\Enums\UserStatus;
use App\Models\TrainingCertificate;
use App\Models\User;
use App\Notifications\Training\CertificateExpiringNotification;
use App\Services\Authorization\DataScopeManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Expiry reminders of certificates: every certificate expiring within setting
 * training.certificate_reminder_days (not expired yet) whose reminder was not sent
 * yet notifies the employee's own account(s) and every active admin holding
 * training.manage who can see the employee (data scope); reminder_sent_at is then
 * set, so a certificate is reminded once (changing its expiry date re-arms it).
 */
class CertificateReminders
{
    public function __construct(private readonly DataScopeManager $scopes) {}

    /**
     * @return int certificates reminded
     */
    public function send(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $days = (int) setting('training.certificate_reminder_days');
        $managers = $this->managers();
        $count = 0;

        $this->scopes->withoutScoping(function () use ($today, $days, $managers, &$count): void {
            TrainingCertificate::query()->withoutGlobalScopes()
                ->whereNull('reminder_sent_at')->whereNotNull('expires_on')
                ->whereDate('expires_on', '>=', $today->toDateString())
                ->whereDate('expires_on', '<=', $today->addDays($days)->toDateString())
                ->with(['employee' => fn ($query) => $query->withoutGlobalScopes()])
                ->orderBy('id')
                ->each(function (TrainingCertificate $certificate) use ($managers, &$count): void {
                    $recipients = User::query()->where('employee_id', $certificate->employee_id)->where('status', UserStatus::Active)->get()
                        ->merge($managers->filter(fn (User $manager): bool => $manager->employee_id !== $certificate->employee_id
                            && $certificate->employee !== null && $certificate->employee->isVisibleTo($manager)));

                    foreach ($recipients->unique('id') as $user) {
                        $user->notify(new CertificateExpiringNotification($certificate));
                    }

                    $certificate->forceFill(['reminder_sent_at' => now()])->save();
                    $count++;
                });
        });

        return $count;
    }

    /**
     * @return Collection<int, User>
     */
    private function managers(): Collection
    {
        return User::query()->admins()->where('status', UserStatus::Active)->get()->filter(fn (User $user): bool => $user->can('training.manage'))->values();
    }
}
