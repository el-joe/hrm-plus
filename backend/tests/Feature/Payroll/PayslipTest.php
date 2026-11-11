<?php

use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Payroll\PayslipDocument;
use App\Support\Money;
use Carbon\CarbonImmutable;

/*
| Payslips (spec module 9 G, section 5 "My Payroll", API me/payslips):
| issued on finalisation; employees see only their own (portal and API),
| admins need payslips.view inside their data scope; every view / download is
| logged; Arabic and English PDF; payment status with partial and bulk
| payments; payroll register, bank file and payslip exports.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00'));
    payrollSetup();
    $this->employee = paidEmployee('6000.00');
    $this->other = paidEmployee('7000.00');
    $this->account = User::factory()->employee()->create(['employee_id' => $this->employee->id, 'locale' => 'ar']);
    $this->otherAccount = User::factory()->employee()->create(['employee_id' => $this->other->id]);
    $this->run = finalisedRun(calculatedRun(payrollPeriod('2026-10')));
    $this->payslip = Payslip::query()->where('employee_id', $this->employee->id)->sole();
    $this->otherPayslip = Payslip::query()->where('employee_id', $this->other->id)->sole();
});

describe('portal', function () {
    it('lists only the employee\'s own payslips with the year summary', function () {
        $this->actingAs($this->account, 'employee')->get(route('portal.payroll'))
            ->assertOk()->assertSee($this->payslip->number)->assertDontSee($this->otherPayslip->number)
            ->assertSee(Money::format((string) $this->payslip->net_pay));
    });

    it('shows and downloads the own payslip and logs every access', function () {
        $this->actingAs($this->account, 'employee')->get(route('portal.payroll.show', $this->payslip->id))->assertOk()->assertSee($this->payslip->number);
        $this->actingAs($this->account, 'employee')->get(route('portal.payroll.pdf', [$this->payslip->id, 'lang' => 'ar']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        expect(AuditLog::query()->where('event', SensitiveDataAccessLog::PAYSLIP)->where('auditable_id', $this->payslip->id)->count())->toBe(2)
            ->and(AuditLog::query()->where('event', SensitiveDataAccessLog::PAYSLIP)->pluck('meta')->pluck('channel')->unique()->values()->all())->toBe(['portal']);
    });

    it('answers 404 for someone else\'s payslip', function () {
        $this->actingAs($this->account, 'employee')->get(route('portal.payroll.show', $this->otherPayslip->id))->assertNotFound();
        $this->actingAs($this->account, 'employee')->get(route('portal.payroll.pdf', $this->otherPayslip->id))->assertNotFound();
    });

    it('hides void payslips', function () {
        $this->payslip->forceFill(['status' => 'void'])->save();

        $this->actingAs($this->account, 'employee')->get(route('portal.payroll.show', $this->payslip->id))->assertNotFound();
    });
});

describe('API', function () {
    it('lists, shows and serves the PDF of the token owner\'s payslips only', function () {
        $token = $this->account->createToken('phone')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept-Language' => 'en'];

        $this->getJson(route('api.v1.me.payslips.index'), $headers)->assertOk()
            ->assertJsonPath('success', true)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $this->payslip->number)
            ->assertJsonPath('data.0.net_pay', (string) $this->payslip->net_pay);

        $this->getJson(route('api.v1.me.payslips.show', $this->payslip->id), $headers)->assertOk()
            ->assertJsonPath('data.lines.earnings.0.code', 'BASIC')
            ->assertJsonPath('data.lines.earnings.0.amount', '6000.00');

        $this->get(route('api.v1.me.payslips.pdf', $this->payslip->id), $headers)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->getJson(route('api.v1.me.payslips.show', $this->otherPayslip->id), $headers)->assertNotFound();

        $this->getJson(route('api.v1.me.payslips.summary', ['year' => 2026]), $headers)->assertOk()
            ->assertJsonPath('data.count', 1)->assertJsonPath('data.net', (string) $this->payslip->net_pay);

        expect(AuditLog::query()->where('event', SensitiveDataAccessLog::PAYSLIP)->pluck('meta')->pluck('channel')->unique()->values()->all())->toBe(['api']);
    });

    it('refuses admin tokens and anonymous calls', function () {
        $this->getJson(route('api.v1.me.payslips.index'))->assertUnauthorized();
    });
});

describe('admin', function () {
    it('lets payslip viewers open payslips in their scope and logs it', function () {
        $viewer = adminWithRoles('HR Manager');

        $this->actingAs($viewer, 'admin')->get(route('admin.payslips.index'))->assertOk()->assertSee($this->payslip->number);
        $this->actingAs($viewer, 'admin')->get(route('admin.payslips.show', $this->payslip))->assertOk();
        $this->actingAs($viewer, 'admin')->get(route('admin.payslips.pdf', [$this->payslip, 'lang' => 'en']))->assertOk();

        expect(AuditLog::query()->where('event', SensitiveDataAccessLog::PAYSLIP)->where('user_id', $viewer->id)->count())->toBe(2);
    });

    it('hides payslips outside the data scope and from admins without the permission', function () {
        $scoped = adminWithPermissions('payslips.view');
        $scoped->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => Branch::factory()->create()->id]);

        $this->actingAs($scoped, 'admin')->get(route('admin.payslips.show', $this->payslip))->assertNotFound();
        $this->actingAs(adminWithRoles('Attendance Officer'), 'admin')->get(route('admin.payslips.index'))->assertForbidden();
    });

    it('records partial and full payments, then bulk marks the rest paid', function () {
        $finance = adminWithRoles('Finance Officer');
        $half = bcdiv((string) $this->payslip->net_pay, '2', 2);

        $this->actingAs($finance, 'admin')->post(route('admin.payslips.payments.store', $this->payslip), [
            'amount' => $half, 'paid_on' => '2026-11-01', 'method' => 'cash',
        ])->assertSessionHasNoErrors();
        expect($this->payslip->refresh()->payment_status)->toBe(PaymentStatus::PartiallyPaid);

        $this->actingAs($finance, 'admin')->post(route('admin.payslips.payments.store', $this->payslip), [
            'amount' => '999999', 'paid_on' => '2026-11-01', 'method' => 'cash',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($finance, 'admin')->post(route('admin.payslips.mark-paid'), [
            'payslip_ids' => [$this->payslip->id, $this->otherPayslip->id], 'paid_on' => '2026-11-02', 'method' => 'bank_transfer', 'reference' => 'TRX-1',
        ])->assertSessionHasNoErrors();

        expect($this->payslip->refresh()->payment_status)->toBe(PaymentStatus::Paid)
            ->and($this->payslip->amount_paid)->toBe((string) $this->payslip->net_pay)
            ->and($this->otherPayslip->refresh()->payment_reference)->toBe('TRX-1')
            ->and($this->payslip->payments()->count())->toBe(2);

        $this->actingAs(adminWithRoles('Payroll Accountant'), 'admin')->post(route('admin.payslips.mark-paid'), [
            'payslip_ids' => [$this->payslip->id], 'paid_on' => '2026-11-02', 'method' => 'cash',
        ])->assertForbidden();
    });
});

describe('documents and exports', function () {
    it('renders the payslip PDF in Arabic and English (smoke)', function () {
        $document = app(PayslipDocument::class);

        foreach (['ar', 'en'] as $locale) {
            $bytes = $document->pdf($this->payslip, $locale);
            expect(substr($bytes, 0, 5))->toBe('%PDF-')->and(strlen($bytes))->toBeGreaterThan(5000);
        }

        expect($document->data($this->payslip, 'ar'))->toMatchArray(['dir' => 'rtl', 'locale' => 'ar', 'demo' => true])
            ->and($document->data($this->payslip, 'en')['earnings'][0]['code'])->toBe('BASIC');
    });

    it('exports the payroll register and the bank file', function () {
        $accountant = adminWithRoles('Payroll Accountant');

        foreach (['register', 'bank'] as $kind) {
            foreach (['xlsx', 'csv'] as $format) {
                $this->actingAs($accountant, 'admin')->get(route('admin.payroll-runs.export', [$this->run, $kind, 'format' => $format]))
                    ->assertOk()->assertDownload($this->run->reference.'-'.$kind.'.'.$format);
            }
        }

        expect(AuditLog::query()->where('event', SensitiveDataAccessLog::PAYROLL)->count())->toBe(4);
        $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->get(route('admin.payroll-runs.export', [$this->run, 'register']))->assertForbidden();
    });

    it('exports the payslip list', function () {
        $this->actingAs(adminWithRoles('Finance Officer'), 'admin')->get(route('admin.payslips.export', ['format' => 'csv']))->assertOk();
    });
});
