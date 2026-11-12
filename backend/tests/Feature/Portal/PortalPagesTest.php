<?php

use App\Models\User;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;

/*
| Employee portal pass (Phase 9, spec section 5): every page renders for an employee in Arabic (RTL) and
| English (LTR) with the navigation (top bar, "More" menu, bottom bar) and the new pages (documents, account).
*/

dataset('portal-locales', [
    'Arabic' => ['ar', 'rtl'],
    'English' => ['en', 'ltr'],
]);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00'));
    seedLeave();
    $this->employee = attendanceEmployee();
    $this->user = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
});

it('renders every portal page for an employee', function (string $locale, string $direction) {
    $this->user->update(['locale' => $locale]);

    foreach ([
        'portal.home', 'portal.profile', 'portal.attendance', 'portal.leaves', 'portal.payroll', 'portal.loans',
        'portal.requests.index', 'portal.requests.create', 'portal.announcements.index', 'portal.notifications.index',
        'portal.notification-preferences.edit', 'portal.performance', 'portal.training', 'portal.assets',
        'portal.onboarding', 'portal.documents', 'portal.account',
    ] as $route) {
        $this->actingAs($this->user, 'employee')->withSession(['locale' => $locale])->get(route($route))
            ->assertOk()
            ->assertSee('dir="'.$direction.'"', false)
            ->assertSee('data-layout="employee"', false);
    }

    foreach (['employment', 'documents', 'history'] as $tab) {
        $this->actingAs($this->user, 'employee')->withSession(['locale' => $locale])->get(route('portal.profile', $tab))->assertOk();
    }
})->with('portal-locales');

it('shows the home cards with the employee\'s own figures', function () {
    $this->actingAs($this->user, 'employee')->get(route('portal.home'))
        ->assertOk()
        ->assertSee('data-region="home-attendance"', false)
        ->assertSee('data-region="home-tasks"', false)
        ->assertSee('data-region="home-month"', false)
        ->assertSee('data-region="home-leave"', false)
        ->assertSee('data-region="home-payslip"', false)
        ->assertSee('data-region="home-requests"', false)
        ->assertSee('data-region="home-upcoming"', false)
        ->assertSee('data-region="home-announcements"', false)
        ->assertSee('data-region="home-notifications"', false)
        ->assertSee('data-attendance-state="not_checked_in"', false)
        ->assertSee(__('app.home.no_payslip'))
        ->assertSee(__('app.home.no_tasks'));
});

it('lists the new pages in the portal navigation', function () {
    $this->actingAs($this->user, 'employee')->get(route('portal.home'))
        ->assertOk()
        ->assertSee(route('portal.documents'), false)
        ->assertSee(route('portal.account'), false)
        ->assertSee('data-region="portal-more"', false)
        ->assertSee('data-region="portal-more-mobile"', false);
});

it('applies the display settings (date format and Arabic-Indic digits) to portal pages', function () {
    portalSetting('localisation.use_arabic_digits', true);
    portalSetting('localisation.date_format', 'd/m/Y');
    $this->user->update(['locale' => 'ar']);

    $this->actingAs($this->user, 'employee')->withSession(['locale' => 'ar'])->get(route('portal.home'))
        ->assertOk()
        ->assertSee('data-digits="arab"', false)
        ->assertSee('data-date-format="d/m/Y"', false);
});

it('tells a user without an employee profile instead of failing', function () {
    $orphan = User::factory()->employee()->create(['employee_id' => null]);

    $this->actingAs($orphan, 'employee')->get(route('portal.home'))->assertOk()->assertSee('data-notice="no-employee-profile"', false);
    $this->actingAs($orphan, 'employee')->get(route('portal.documents'))->assertNotFound();
});

function portalSetting(string $key, mixed $value): void
{
    app(SettingsService::class)->set($key, $value);
}
