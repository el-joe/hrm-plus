<?php

namespace App\Http\Controllers\Admin\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateAttachment;
use App\Models\JobApplication;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Recruitment\RecruitmentFileStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Internal notes and attachments of a candidate.
 */
class CandidateRecordController extends Controller
{
    public function storeNote(Request $request, Candidate $candidate): RedirectResponse
    {
        Gate::authorize('update', $candidate);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'job_application_id' => ['nullable', 'integer', Rule::exists(JobApplication::class, 'id')->where('candidate_id', $candidate->id)],
        ]);

        $candidate->notes()->create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('status', __('recruitment.messages.note_added'));
    }

    public function storeAttachment(Request $request, Candidate $candidate, RecruitmentFileStore $files): RedirectResponse
    {
        Gate::authorize('update', $candidate);

        $types = implode(',', (array) setting('documents.allowed_extensions'));
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimes:'.$types, 'extensions:'.$types, 'max:'.config('recruitment.attachment_max_kb')],
        ]);

        $stored = $files->store($candidate, $request->file('file'), 'attachments');
        $candidate->attachments()->create([
            'title' => $data['title'],
            'file_path' => $stored['path'],
            'file_name' => $stored['name'],
            'file_mime' => $stored['mime'],
            'file_size' => $stored['size'],
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('status', __('recruitment.messages.attachment_added'));
    }

    public function downloadAttachment(Request $request, Candidate $candidate, CandidateAttachment $attachment, RecruitmentFileStore $files, SensitiveDataAccessLog $log): StreamedResponse
    {
        Gate::authorize('view', $candidate);
        $log->record(SensitiveDataAccessLog::CANDIDATE_FILE, $attachment, ['file'], ['candidate_id' => $candidate->id], $request->user());

        return $files->response($attachment->file_path, $attachment->file_name, $attachment->file_mime);
    }

    public function destroyAttachment(Candidate $candidate, CandidateAttachment $attachment, RecruitmentFileStore $files): RedirectResponse
    {
        Gate::authorize('update', $candidate);

        $attachment->delete();
        $files->delete($attachment->file_path);

        return back()->with('status', __('recruitment.messages.attachment_deleted'));
    }
}
