<?php

namespace Database\Seeders;

use App\Actions\Announcements\ManageAnnouncement;
use App\Models\Announcement;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\EmploymentType;
use App\Models\User;
use App\Services\Announcements\AnnouncementAudience;
use App\Services\Announcements\AnnouncementFileStore;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Demo announcements (Phase 8b), created through ManageAnnouncement (sanitised bodies, scope checks,
 * real in-app notifications to the audience's accounts):
 *
 * 1. company-wide policy update, pinned, acknowledgement required, PDF attached (employee1@ read and
 *    acknowledged it, employee3@ read it only, employee2@ has not opened it);
 * 2. branch ALX (Alexandria) inventory day — written by branch.manager@ (data scope ALX);
 * 3. department SAL (with Sales Cairo / Alexandria) incentive scheme, PDF attached;
 * 4. employee group FIELD safety equipment notice (important);
 * 5. company-wide important notice published today (e-mail + push);
 * 6. interns (employment type INT) orientation;
 * 7. scheduled in three days; 8. expired; 9. draft; 10. archived.
 *
 * Reads / acknowledgements are recorded for a realistic share of each audience. Skipped when
 * announcements exist.
 */
class AnnouncementDemoSeeder extends Seeder
{
    private ManageAnnouncement $announcements;

    public function run(): void
    {
        if (Announcement::query()->exists() || ! Employee::query()->withoutGlobalScopes()->exists()) {
            return;
        }

        config(['queue.default' => 'sync']);
        // Earlier demo seeders fake notifications in this process; Phase 8b demo data goes through the real centre.
        Notification::swap(new ChannelManager(app()));

        $this->announcements = app(ManageAnnouncement::class);
        $hr = User::query()->where('email', 'hr.manager@hrmplus.test')->firstOrFail();
        $branchManager = User::query()->where('email', 'branch.manager@hrmplus.test')->firstOrFail();
        $employee1 = User::query()->where('email', 'employee1@hrmplus.test')->firstOrFail()->employee_id;
        $employee2 = User::query()->where('email', 'employee2@hrmplus.test')->firstOrFail()->employee_id;
        $employee3 = User::query()->where('email', 'employee3@hrmplus.test')->firstOrFail()->employee_id;
        $today = CarbonImmutable::today();

        // 1. Pinned policy update requiring acknowledgement.
        $policy = $this->at($today->subDays(10), fn (): Announcement => $this->announcements->save([
            'title_ar' => 'تحديث سياسة العمل عن بُعد',
            'title_en' => 'Updated remote work policy',
            'body_ar' => '<p>اعتبارًا من الشهر القادم يحق لجميع الموظفين العمل عن بُعد <strong>يومين في الأسبوع</strong> بعد موافقة المدير المباشر.</p><ul><li>يُطلب التقديم عبر بوابة الموظفين قبل الأسبوع بثلاثة أيام.</li><li>تبقى أيام الاجتماعات الأسبوعية حضورية.</li><li>يجب الالتزام بساعات العمل الأساسية من 10 صباحًا حتى 3 عصرًا.</li></ul><p>يرجى قراءة السياسة المرفقة والإقرار بالاطلاع.</p>',
            'body_en' => '<p>From next month every employee may work remotely <strong>two days a week</strong> with their direct manager\'s approval.</p><ul><li>Request it in the employee portal three days before the week starts.</li><li>Weekly meeting days remain on-site.</li><li>Core hours 10:00–15:00 apply on remote days.</li></ul><p>Please read the attached policy and acknowledge it.</p>',
            'type' => 'policy_update', 'audience' => Announcement::AUDIENCE_ALL,
            'is_pinned' => true, 'requires_acknowledgement' => true, 'notify_email' => true, 'notify_push' => true,
        ], $hr, publish: true));
        $this->attach($policy, 'remote-work-policy.pdf', 'Remote work policy v2 - Horizon Integrated Solutions', $hr);
        $this->reads($policy, $today->subDays(10), readEvery: 10, readShare: 8, acknowledgeShare: 6, force: [$employee1 => 'ack', $employee3 => 'read'], skip: [$employee2]);

        // 2. Branch ALX, written by the branch manager (restricted to ALX).
        $inventory = $this->at($today->subDays(5), fn (): Announcement => $this->announcements->save([
            'title_ar' => 'يوم الجرد السنوي لمستودع الإسكندرية',
            'title_en' => 'Annual inventory day — Alexandria warehouse',
            'body_ar' => '<p>سيُغلق المستودع أمام الحركة يوم الخميس القادم لإجراء الجرد السنوي. يرجى من جميع موظفي فرع الإسكندرية الحضور الساعة 8 صباحًا.</p>',
            'body_en' => '<p>The warehouse closes for movements next Thursday for the annual stock count. All Alexandria staff please be on site at 08:00.</p>',
            'type' => 'event', 'audience' => Announcement::AUDIENCE_TARGETED,
            'targets' => ['branch' => [Branch::query()->withoutGlobalScopes()->where('code', 'ALX')->value('id')]],
            'notify_push' => true,
        ], $branchManager, publish: true));
        $this->reads($inventory, $today->subDays(5), readEvery: 10, readShare: 5, force: [$employee3 => 'read']);

        // 3. Sales department (and its sub-departments).
        $incentive = $this->at($today->subDays(3), fn (): Announcement => $this->announcements->save([
            'title_ar' => 'برنامج حوافز المبيعات للربع الرابع',
            'title_en' => 'Q4 sales incentive scheme',
            'body_ar' => '<p>يسرنا الإعلان عن برنامج حوافز الربع الرابع: <em>مكافأة 5%</em> عند تحقيق 110% من المستهدف. التفاصيل في الملف المرفق.</p>',
            'body_en' => '<p>We are pleased to announce the Q4 incentive scheme: an <em>extra 5%</em> bonus at 110% of target. Details are in the attached file.</p>',
            'type' => 'general', 'audience' => Announcement::AUDIENCE_TARGETED,
            'targets' => ['department' => [Department::query()->withoutGlobalScopes()->where('code', 'SAL')->value('id')]],
            'notify_push' => true,
        ], $hr, publish: true));
        $this->attach($incentive, 'q4-incentive-scheme.pdf', 'Q4 incentive scheme - targets and payout table', $hr);
        $this->reads($incentive, $today->subDays(3), readEvery: 10, readShare: 6, force: [$employee1 => 'read']);

        // 4. Employee group FIELD.
        $safety = $this->at($today->subDay(), fn (): Announcement => $this->announcements->save([
            'title_ar' => 'استلام معدات السلامة للفرق الميدانية',
            'title_en' => 'Safety equipment collection for field staff',
            'body_ar' => '<p>على جميع أعضاء الفرق الميدانية استلام الخوذ والسترات العاكسة الجديدة من المخزن قبل نهاية الأسبوع.</p>',
            'body_en' => '<p>All field staff must collect their new helmets and high-visibility vests from the store before the end of the week.</p>',
            'type' => 'important', 'audience' => Announcement::AUDIENCE_TARGETED,
            'targets' => ['employee_group' => [EmployeeGroup::query()->where('code', 'FIELD')->value('id')]],
            'notify_push' => true,
        ], $hr, publish: true));
        $this->reads($safety, $today->subDay(), readEvery: 10, readShare: 3);

        // 5. Company-wide important notice, today.
        $closure = $this->at($today->setTime(9, 0), fn (): Announcement => $this->announcements->save([
            'title_ar' => 'إغلاق المكاتب بمناسبة العطلة الرسمية',
            'title_en' => 'Offices closed for the public holiday',
            'body_ar' => '<p>تُغلق جميع مكاتب الشركة يوم الأحد القادم بمناسبة العطلة الرسمية، وتُستأنف الأعمال يوم الإثنين.</p>',
            'body_en' => '<p>All company offices are closed next Sunday for the public holiday; work resumes on Monday.</p>',
            'type' => 'important', 'audience' => Announcement::AUDIENCE_ALL, 'notify_email' => true, 'notify_push' => true,
        ], $hr, publish: true));
        $this->reads($closure, $today->setTime(9, 0), readEvery: 10, readShare: 2);

        // 6. Interns (employment type INT).
        $this->at($today->subDays(2), fn (): Announcement => $this->announcements->save([
            'title_ar' => 'جلسة تعريفية للمتدربين',
            'title_en' => 'Orientation session for interns',
            'body_ar' => '<p>ندعو جميع المتدربين إلى جلسة تعريفية بقاعة الاجتماعات الكبرى يوم الثلاثاء الساعة 11 صباحًا.</p>',
            'body_en' => '<p>All interns are invited to an orientation session in the main meeting room on Tuesday at 11:00.</p>',
            'type' => 'event', 'audience' => Announcement::AUDIENCE_TARGETED,
            'targets' => ['employment_type' => [EmploymentType::query()->where('code', 'INT')->value('id')]],
            'notify_push' => true,
        ], $hr, publish: true));

        // 7. Scheduled in three days (notified by announcements:publish-scheduled).
        $this->announcements->save([
            'title_ar' => 'الحفل السنوي للشركة',
            'title_en' => 'Annual company gathering',
            'body_ar' => '<p>يسعدنا دعوتكم إلى الحفل السنوي للشركة. سيتم إرسال تفاصيل المكان والبرنامج قريبًا.</p>',
            'body_en' => '<p>You are invited to the annual company gathering. Venue and programme details will follow.</p>',
            'type' => 'event', 'audience' => Announcement::AUDIENCE_ALL, 'notify_push' => true,
            'publish_at' => $today->addDays(3)->setTime(9, 0)->toDateTimeString(),
        ], $hr, publish: true);

        // 8. Expired (Ramadan working hours).
        $this->at($today->subDays(40), fn (): Announcement => $this->announcements->save([
            'title_ar' => 'مواعيد العمل خلال شهر رمضان',
            'title_en' => 'Working hours during Ramadan',
            'body_ar' => '<p>تكون مواعيد العمل خلال الشهر الكريم من 9 صباحًا حتى 3 عصرًا.</p>',
            'body_en' => '<p>Working hours during the holy month are 09:00 to 15:00.</p>',
            'type' => 'general', 'audience' => Announcement::AUDIENCE_ALL,
            'expires_at' => $today->subDays(10)->toDateTimeString(),
        ], $hr, publish: true));

        // 9. Draft.
        $this->announcements->save([
            'title_ar' => 'تجديد وثيقة التأمين الطبي (مسودة)',
            'title_en' => 'Medical insurance renewal (draft)',
            'body_ar' => '<p>سيتم تجديد وثيقة التأمين الطبي اعتبارًا من يناير مع إضافة تغطية الأسنان.</p>',
            'body_en' => '<p>The medical insurance policy renews in January and adds dental cover.</p>',
            'type' => 'policy_update', 'audience' => Announcement::AUDIENCE_ALL, 'requires_acknowledgement' => true,
        ], $hr);

        // 10. Archived.
        $parking = $this->at($today->subDays(60), fn (): Announcement => $this->announcements->save([
            'title_ar' => 'تنظيم مواقف السيارات',
            'title_en' => 'Car park allocation',
            'body_ar' => '<p>تم تخصيص الطابق السفلي لمواقف الموظفين.</p>',
            'body_en' => '<p>The basement level is now reserved for staff parking.</p>',
            'type' => 'general', 'audience' => Announcement::AUDIENCE_ALL,
        ], $hr, publish: true));
        $this->at($today->subDays(30), fn () => $this->announcements->archive($parking, $hr));
    }

    /**
     * Record reads (and acknowledgements) for a deterministic share of the audience: of every
     * $readEvery employees, $readShare read it and $acknowledgeShare of those acknowledged it.
     *
     * @param  array<int, 'read'|'ack'>  $force  employee id => outcome
     * @param  list<int>  $skip  employees who must not have read it
     */
    private function reads(Announcement $announcement, CarbonImmutable $from, int $readEvery, int $readShare, int $acknowledgeShare = 0, array $force = [], array $skip = []): void
    {
        $user = User::query()->where('email', 'hr.manager@hrmplus.test')->firstOrFail();
        $employees = app(AnnouncementAudience::class)->employees($announcement)->orderBy('employees.id')->get();

        foreach ($employees as $index => $employee) {
            $outcome = $force[$employee->id] ?? null;

            if ($outcome === null) {
                if (in_array($employee->id, $skip, true) || $index % $readEvery >= $readShare) {
                    continue;
                }

                $outcome = $index % $readEvery < $acknowledgeShare ? 'ack' : 'read';
            }

            $moment = $from->addHours(1 + ($index * 7) % 60);
            $moment = $moment->isFuture() ? CarbonImmutable::now()->subMinutes(5 + $index) : $moment;

            $this->at($moment, function () use ($announcement, $employee, $outcome, $user): void {
                if ($outcome === 'ack' && $announcement->requires_acknowledgement) {
                    $this->announcements->acknowledge($announcement, $employee, $employee->user ?? $user, 'portal');
                } else {
                    $this->announcements->markRead($announcement, $employee);
                }
            });
        }
    }

    private function attach(Announcement $announcement, string $name, string $text, User $by): void
    {
        $announcement->attachments()->create([
            ...app(AnnouncementFileStore::class)->storeContents($announcement, $this->pdf($text), $name, 'application/pdf'),
            'uploaded_by' => $by->id,
        ]);
    }

    /**
     * A minimal one-page PDF with one line of text (demo attachment).
     */
    private function pdf(string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = "BT /F1 14 Tf 72 760 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($number + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function at(CarbonImmutable $moment, callable $callback): mixed
    {
        $moment = $moment->hour === 0 ? $moment->setTime(10, 0) : $moment;
        // Never in the future (the demo may be seeded early in the morning).
        $now = CarbonImmutable::now();
        Carbon::setTestNow($moment->greaterThan($now) ? $now->subMinutes(30) : $moment);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
