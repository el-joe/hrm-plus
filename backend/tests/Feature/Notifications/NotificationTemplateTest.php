<?php

use App\Models\NotificationTemplate;
use App\Models\TrainingCertificate;
use App\Notifications\Training\CertificateExpiringNotification;
use App\Services\Notifications\NotificationEventRegistry;
use App\Services\Notifications\NotificationTemplates;
use App\Services\Notifications\TemplateRenderer;
use Database\Seeders\NotificationDefaultsSeeder;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/NotificationHelpers.php';

/*
| Notification templates (Phase 8b): {{placeholder}} rendering with a per-event whitelist (no Blade, no
| eval), locale fallback, defaults seeded from the lang files, reset, and the refactored notification
| classes rendering through them.
*/

it('renders only whitelisted placeholders and never evaluates code', function () {
    $renderer = app(TemplateRenderer::class);

    expect($renderer->render('Hi {{ employee }} {{secret}} {{ 1+1 }} @php echo 1; @endphp', ['employee' => 'Sara', 'secret' => 'x'], ['employee']))
        ->toBe('Hi Sara {{ 1+1 }} @php echo 1; @endphp')
        ->and($renderer->render('<b>{{employee}}</b>', ['employee' => '<script>'], ['employee']))->toBe('<b><script></b>');
});

it('keeps every default text inside its placeholder whitelist', function () {
    $events = app(NotificationEventRegistry::class);
    $renderer = app(TemplateRenderer::class);

    foreach (array_keys($events->all()) as $event) {
        foreach (['ar', 'en'] as $locale) {
            $text = $events->defaultText($event, $locale);

            expect(trans()->has($events->get($event)['title'], $locale))->toBeTrue("Missing title of {$event} ({$locale})")
                ->and(trans()->has($events->get($event)['message'], $locale))->toBeTrue("Missing message of {$event} ({$locale})")
                ->and(preg_match('/(?<![A-Za-z0-9]):[a-z_]+/', $text['subject'].' '.$text['body'], $left))->toBe(0, "Unlisted placeholder {$event} ({$locale}): ".($left[0] ?? ''))
                ->and($renderer->unknownPlaceholders($text['subject'].$text['body'], $events->placeholders($event)))->toBe([]);
        }
    }
});

it('seeds one template per event, channel and language without overwriting edits', function () {
    $this->seed(NotificationDefaultsSeeder::class);
    $count = count(app(NotificationEventRegistry::class)->all()) * 3 * 2;
    expect(NotificationTemplate::query()->count())->toBe($count);

    NotificationTemplate::query()->where(['event' => 'birthday_greeting', 'channel' => 'database', 'locale' => 'en'])->update(['body' => 'Edited']);
    $this->seed(NotificationDefaultsSeeder::class);

    expect(NotificationTemplate::query()->count())->toBe($count)
        ->and(NotificationTemplate::query()->where(['event' => 'birthday_greeting', 'channel' => 'database', 'locale' => 'en'])->value('body'))->toBe('Edited');
});

it('refuses placeholders the event does not offer', function () {
    $templates = app(NotificationTemplates::class);

    expect(fn () => $templates->save('birthday_greeting', 'mail', 'en', 'Hi {{employee}}', 'Salary {{amount}}', superAdmin()))
        ->toThrow(ValidationException::class);
});

it('falls back to the other language, then to the default lang text', function () {
    $templates = app(NotificationTemplates::class);
    $admin = superAdmin();

    // Nothing stored: the default Arabic lang text.
    expect($templates->render('birthday_greeting', 'database', 'ar', ['employee' => 'سارة', 'company' => 'X'])['subject'])->toContain('سارة');

    // Only English stored: Arabic recipients get the English row (fallback locale).
    $templates->save('birthday_greeting', 'database', 'en', 'Happy day {{employee}}', 'From {{company}}', $admin);
    NotificationTemplate::query()->where(['event' => 'birthday_greeting', 'channel' => 'database', 'locale' => 'ar'])->delete();

    expect($templates->render('birthday_greeting', 'database', 'ar', ['employee' => 'Sara', 'company' => 'X'])['subject'])->toBe('Happy day Sara');
});

it('marks edits as customised and resets them to the default text', function () {
    $templates = app(NotificationTemplates::class);
    $admin = superAdmin();

    $row = $templates->save('birthday_greeting', 'push', 'en', 'Custom {{employee}}', 'Body', $admin);
    expect($row->is_customised)->toBeTrue();

    $templates->reset('birthday_greeting', $admin, 'push', 'en');

    $row->refresh();
    expect($row->is_customised)->toBeFalse()
        ->and($row->subject)->toBe(app(NotificationEventRegistry::class)->defaultText('birthday_greeting', 'en')['subject']);
});

it('renders the existing notification classes through the templates in the recipient language', function () {
    seedRoles();
    $user = employeeAccount(user: ['locale' => 'en']);
    $certificate = TrainingCertificate::factory()->create(['employee_id' => $user->employee_id, 'name' => 'First aid', 'expires_on' => now()->addDays(10)]);
    app(NotificationTemplates::class)->save('training_certificate_expiring_own', 'database', 'en', 'Renew {{name}}', '{{name}} ends {{date}}', superAdmin());

    $data = (new CertificateExpiringNotification($certificate))->toArray($user);

    expect($data['kind'])->toBe('training_certificate_expiring')
        ->and($data['title'])->toBe('Renew First aid')
        ->and($data['message'])->toBe('First aid ends '.$certificate->expires_on->toDateString())
        ->and($data['url'])->toBe(route('portal.training'));
});

it('previews unsaved text with sample values on the edit screen', function () {
    seedRoles();
    $this->actingAs(adminWithRoles('HR Manager'), 'admin')
        ->put(route('admin.notification-templates.update', 'birthday_greeting'), [
            'channel' => 'database', 'locale' => 'en', 'subject' => 'Hi {{employee}}', 'body' => 'b', 'intent' => 'preview',
        ])->assertRedirect();

    expect(NotificationTemplate::query()->where('subject', 'Hi {{employee}}')->exists())->toBeFalse();

    $this->get(route('admin.notification-templates.edit', ['event' => 'birthday_greeting', 'channel' => 'database', 'locale' => 'en']))
        ->assertOk()->assertSee('Hi '.trans('notifications.samples.employee', [], 'en'));
});

it('lets notifications.view read templates but only notifications.manage edit them', function () {
    $viewer = adminWithPermissions('notifications.view');

    $this->actingAs($viewer, 'admin')->get(route('admin.notification-templates.index'))->assertOk();
    $this->actingAs($viewer, 'admin')->put(route('admin.notification-templates.update', 'birthday_greeting'), [
        'channel' => 'mail', 'locale' => 'en', 'subject' => 'x', 'body' => 'y',
    ])->assertForbidden();
    $this->actingAs(adminWithPermissions('dashboard.view'), 'admin')->get(route('admin.notification-templates.index'))->assertForbidden();
});
