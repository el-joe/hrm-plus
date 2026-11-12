<?php

use App\Services\Settings\SettingsService;
use App\Support\Formatter;
use Carbon\CarbonImmutable;

/*
| Phase 8c — the central display formatter applies the localisation settings: date format, time
| format and Arabic-Indic digits (Arabic interface only). Written, NOT run.
*/

beforeEach(function () {
    app(SettingsService::class)->set('localisation.timezone', 'Africa/Cairo');
});

it('formats dates with the configured date format', function (string $format, string $expected) {
    app(SettingsService::class)->set('localisation.date_format', $format);

    expect(Formatter::date('2026-03-07'))->toBe($expected)
        ->and(Formatter::date(CarbonImmutable::parse('2026-03-07')))->toBe($expected)
        ->and(Formatter::date(null))->toBe('');
})->with([
    ['Y-m-d', '2026-03-07'],
    ['d/m/Y', '07/03/2026'],
    ['m/d/Y', '03/07/2026'],
]);

it('shows instants in the company timezone with the time format', function () {
    app(SettingsService::class)->set('localisation.time_format', 'h:i A');
    app()->setLocale('en');

    expect(Formatter::time(CarbonImmutable::parse('2026-03-07 13:05:00', 'UTC')))->toBe('03:05 PM')
        ->and(Formatter::dateTime(CarbonImmutable::parse('2026-03-07 23:30:00', 'UTC')))->toBe('2026-03-08 01:30 AM');
});

it('uses Arabic-Indic digits only in Arabic and only when the setting is on', function () {
    app(SettingsService::class)->set('localisation.use_arabic_digits', true);

    app()->setLocale('ar');
    expect(Formatter::number(1234567))->toBe('١,٢٣٤,٥٦٧')
        ->and(Formatter::date('2026-03-07'))->toBe('٢٠٢٦-٠٣-٠٧')
        ->and(Formatter::money('1500.5', 2))->toBe('١,٥٠٠.٥٠');

    app()->setLocale('en');
    expect(Formatter::number(1234567))->toBe('1,234,567');

    app(SettingsService::class)->set('localisation.use_arabic_digits', false);
    app()->setLocale('ar');
    expect(Formatter::number(42))->toBe('42');
});

it('formats amounts with bcmath and never through float', function () {
    expect(Formatter::money('12345678901234.99', 2))->toBe('12,345,678,901,234.99')
        ->and(Formatter::number('1000.456', 2))->toBe('1,000.45')
        ->and(Formatter::percent(12.345, 1))->toBe('12.3%');
});

it('renders the x-date and x-number components and exposes the settings to JavaScript', function () {
    app(SettingsService::class)->set('localisation.use_arabic_digits', true);
    app(SettingsService::class)->set('localisation.date_format', 'd/m/Y');
    app(SettingsService::class)->set('localisation.default_locale', 'ar');
    app()->setLocale('ar');

    $date = (string) $this->blade('<x-date :value="$d" />', ['d' => '2026-03-07']);
    $number = (string) $this->blade('<x-number :value="$n" />', ['n' => 1200]);

    expect($date)->toContain('٠٧/٠٣/٢٠٢٦')->toContain('datetime="2026-03-07"')
        ->and($number)->toContain('١,٢٠٠');

    $this->actingAs(superAdmin(), 'admin')->get(route('admin.dashboard'))
        ->assertSee('data-digits="arab"', false)->assertSee('data-date-format="d/m/Y"', false);
});
