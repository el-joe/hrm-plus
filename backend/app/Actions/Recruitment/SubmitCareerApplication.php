<?php

namespace App\Actions\Recruitment;

use App\Enums\CandidateSource;
use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\Vacancy;
use App\Notifications\Recruitment\ApplicationReceived;
use App\Services\Recruitment\CandidateDuplicates;
use App\Services\Recruitment\CandidateNotifier;
use App\Services\Recruitment\RecruitmentFileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * An application from the public career page. A returning applicant (same
 * e-mail or phone) is matched to their existing candidate record, which gets
 * the new CV and any contact details it lacked; nothing they typed
 * overwrites data HR already has. The candidate receives a confirmation.
 */
class SubmitCareerApplication
{
    public function __construct(
        private readonly CandidateDuplicates $duplicates,
        private readonly RecruitmentFileStore $files,
        private readonly ApplyToVacancy $apply,
        private readonly CandidateNotifier $notifier,
    ) {}

    /**
     * @param  array{name: string, email: string, phone: string, city?: string|null, current_job_title?: string|null, experience_years?: string|null, linkedin_url?: string|null, cover_letter?: string|null}  $data
     */
    public function handle(Vacancy $vacancy, array $data, UploadedFile $cv, string $locale): JobApplication
    {
        $application = DB::transaction(function () use ($vacancy, $data, $cv, $locale): JobApplication {
            $candidate = $this->duplicates->find($data['email'], $data['phone'])->first();
            $nameColumn = preg_match('/\p{Arabic}/u', $data['name']) ? 'name_ar' : 'name_en';

            if ($candidate === null) {
                $candidate = new Candidate([
                    $nameColumn => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'city' => $data['city'] ?? null,
                    'current_job_title' => $data['current_job_title'] ?? null,
                    'experience_years' => $data['experience_years'] ?? null,
                    'linkedin_url' => $data['linkedin_url'] ?? null,
                    'source' => CandidateSource::CareerPage,
                ]);
            } else {
                foreach ([$nameColumn => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'city' => $data['city'] ?? null,
                    'current_job_title' => $data['current_job_title'] ?? null, 'linkedin_url' => $data['linkedin_url'] ?? null] as $column => $value) {
                    if (blank($candidate->getAttribute($column)) && filled($value)) {
                        $candidate->setAttribute($column, $value);
                    }
                }
            }

            $candidate->forceFill(['consent_at' => now(), 'locale' => $locale])->save();
            $application = $this->apply->handle($candidate, $vacancy, CandidateSource::CareerPage, $data['cover_letter'] ?? null);
            $this->files->storeCv($candidate, $cv);

            return $application;
        });

        $this->notifier->send($application->candidate, new ApplicationReceived($application));

        return $application;
    }
}
