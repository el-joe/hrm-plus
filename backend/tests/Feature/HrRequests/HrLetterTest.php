<?php

use App\Actions\HrRequests\IssueHrLetter;
use App\Enums\HrRequestStatus;
use App\Models\HrLetterTemplate;
use App\Services\HrRequests\HrLetterDocument;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/HrRequestHelpers.php';

/*
| Letters / certificates (Phase 8b): rendered from the template with the placeholder whitelist (no Blade),
| issued as a PDF snapshot, downloadable by the employee only, salary letters need financial access.
*/

beforeEach(function () {
    seedHrRequests();
    Notification::fake();
    Storage::fake('hr_request_files');

    [$this->employee, $this->user] = hrRequestEmployee(['name_en' => 'Mona Fawzy', 'name_ar' => 'منى فوزي']);
    $this->hr = adminWithRoles('HR Manager');
});

it('renders only whitelisted placeholders and never evaluates code', function () {
    $template = HrLetterTemplate::factory()->create(['body_en' => 'Dear {{employee_name}} {{ @php echo 1; @endphp }} {{unknown}} {!! 2 !!}']);
    $document = app(HrLetterDocument::class);

    $text = $document->renderBody($template, 'en', $document->values($this->employee, 'en'));

    expect($text)->toContain('Dear Mona Fawzy')->not->toContain('unknown')->toContain('{!! 2 !!}');
    expect($document->unknownPlaceholders('{{employee_name}} {{salary}}'))->toBe(['salary']);
});

it('issues the letter as a PDF in the requested language and resolves the request', function () {
    $request = fileHrRequest($this->employee, $this->user, 'EMPLOYMENT_CERT', ['letter_language' => 'en', 'fields' => ['addressee' => 'Embassy']]);

    $this->actingAs($this->hr, 'admin')->post(route('admin.hr-requests.letter', $request), ['language' => 'en', 'resolve' => 1])->assertRedirect();

    $request->refresh();
    $letter = $request->letter();
    expect($letter)->not->toBeNull()
        ->and($letter->file_mime)->toBe('application/pdf')
        ->and($letter->file_name)->toContain('-en.pdf')
        ->and($request->status)->toBe(HrRequestStatus::Resolved)
        ->and($request->events->pluck('type')->all())->toContain('letter_issued');
    expect(Storage::disk('hr_request_files')->get($letter->file_path))->toStartWith('%PDF');

    $this->actingAs($this->user, 'employee')->get(route('portal.requests.attachments.download', [$request->id, $letter->id]))->assertOk();
    Sanctum::actingAs($this->user);
    $this->get(route('api.v1.me.requests.attachments.show', [$request->id, $letter->id]))->assertOk();
});

it('never lets another employee download the letter', function () {
    $request = fileHrRequest($this->employee, $this->user, 'EMPLOYMENT_CERT');
    $letter = app(IssueHrLetter::class)->handle($request, $this->hr);
    [, $other] = hrRequestEmployee();

    $this->actingAs($other, 'employee')->get(route('portal.requests.attachments.download', [$request->id, $letter->id]))->assertNotFound();
});

it('needs financial access to issue a letter that shows the salary', function () {
    $request = fileHrRequest($this->employee, $this->user, 'SALARY_CERT');
    $specialist = adminWithRoles('HR Specialist');

    $this->actingAs($specialist, 'admin')->post(route('admin.hr-requests.letter', $request))->assertForbidden();
    $this->actingAs($this->hr, 'admin')->post(route('admin.hr-requests.letter', $request))->assertRedirect();

    expect($request->refresh()->letter())->not->toBeNull();
});

it('refuses unknown placeholders when a template is saved', function () {
    $this->actingAs($this->hr, 'admin')->post(route('admin.hr-letter-templates.store'), [
        'code' => 'NOC', 'name_ar' => 'عدم ممانعة', 'name_en' => 'No objection', 'is_active' => 1,
        'body_ar' => 'نص {{employee_name}}', 'body_en' => 'Text {{password}}',
    ])->assertSessionHasErrors('body_en');

    expect(HrLetterTemplate::query()->where('code', 'NOC')->exists())->toBeFalse();
});
