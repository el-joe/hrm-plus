<?php

namespace App\Actions\HrRequests;

use App\Enums\HrRequestMessageType;
use App\Models\HrRequest;
use App\Models\HrRequestAttachment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\HrRequests\HrLetterDocument;
use App\Services\HrRequests\HrRequestFileStore;
use App\Services\HrRequests\HrRequestHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generates the letter of a "letter" request from the category's template in the language the employee
 * chose, stores the PDF on the request (a snapshot: later template edits do not change it), adds an
 * employee-visible thread line and — by default — resolves the request.
 *
 * A template showing pay ({{basic_salary}}) needs employee_financials.view (checked by HrRequestPolicy::issueLetter).
 */
class IssueHrLetter
{
    public function __construct(
        private readonly HrLetterDocument $document,
        private readonly HrRequestFileStore $files,
        private readonly HrRequestHistory $history,
        private readonly ManageHrRequest $manage,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(HrRequest $request, User $actor, ?string $language = null, bool $resolve = true): HrRequestAttachment
    {
        $request->loadMissing('category.letterTemplate');
        $template = $request->category->letterTemplate;

        if (! $request->category->isLetter() || $template === null || ! $template->is_active) {
            throw ValidationException::withMessages(['letter' => __('hr_requests.validation.no_letter_template')]);
        }

        if (! $request->status->isHandled()) {
            throw ValidationException::withMessages(['letter' => __('hr_requests.validation.not_handled')]);
        }

        $language = in_array($language, ['ar', 'en'], true) ? $language : ($request->letter_language ?? 'ar');
        $bytes = $this->document->pdf($template, $request, $language);

        $attachment = DB::transaction(function () use ($request, $actor, $template, $language, $bytes): HrRequestAttachment {
            $attachment = $request->attachments()->create([
                ...$this->files->storeContents($request, $bytes, $this->document->fileName($template, $request, $language), 'application/pdf'),
                'is_letter' => true,
                'uploaded_by' => $actor->id,
            ]);
            $request->messages()->create([
                'type' => HrRequestMessageType::System,
                'body' => __('hr_requests.letters.issued_line', ['name' => $template->localized('name', $language)], $language),
                'author_id' => $actor->id,
            ]);
            $this->history->record($request, 'letter_issued', $actor, null, $template->code.'/'.$language);
            $this->audit->log('hr_request.letter_issued', $request, meta: ['template' => $template->code, 'language' => $language], actor: $actor);

            return $attachment;
        });

        if ($resolve) {
            $this->manage->resolve($request->refresh(), $actor, null);
        }

        return $attachment;
    }
}
