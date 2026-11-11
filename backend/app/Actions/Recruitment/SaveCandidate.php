<?php

namespace App\Actions\Recruitment;

use App\Enums\CandidateSource;
use App\Models\Candidate;
use App\Models\CandidateTag;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Recruitment\RecruitmentFileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create or edit a candidate profile (admin side): personal and
 * professional data, CV, tags; on create optionally the first application.
 * Salary keys are only present in $data when the admin may set them (the
 * Form Request excludes them otherwise).
 */
class SaveCandidate
{
    public function __construct(
        private readonly RecruitmentFileStore $files,
        private readonly ApplyToVacancy $apply,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Candidate $candidate, array $data, User $actor, ?UploadedFile $cv = null): Candidate
    {
        return DB::transaction(function () use ($candidate, $data, $actor, $cv): Candidate {
            $creating = $candidate === null;
            $candidate ??= new Candidate;

            $data['skills'] = $this->list($data['skills'] ?? null);
            $candidate->fill($data);

            if ($creating) {
                $candidate->forceFill(['created_by' => $actor->getKey(), 'locale' => $data['locale'] ?? app()->getLocale()]);
            } elseif (array_key_exists('locale', $data)) {
                $candidate->locale = $data['locale'];
            }

            $candidate->save();

            if ($cv !== null) {
                $this->files->storeCv($candidate, $cv);
            }

            if (array_key_exists('tags', $data)) {
                $candidate->tags()->sync($this->tagIds($data['tags']));
            }

            if ($creating && filled($data['vacancy_id'] ?? null)) {
                $vacancy = Vacancy::query()->findOrFail($data['vacancy_id']);
                $this->apply->handle($candidate, $vacancy, CandidateSource::from($data['source']), null, $actor);
            }

            return $candidate;
        });
    }

    /**
     * "php, laravel , sql" → ['php', 'laravel', 'sql'] (max 30 items of 50 chars).
     *
     * @return list<string>|null
     */
    private function list(mixed $value): ?array
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        }

        $items = collect(preg_split('/[,،\n]+/u', (string) $value) ?: [])
            ->map(fn (string $item): string => Str::limit(trim($item), 50, ''))
            ->filter()->unique()->take(30)->values()->all();

        return $items === [] ? null : $items;
    }

    /**
     * @return list<int>
     */
    private function tagIds(mixed $tags): array
    {
        return collect($this->list($tags) ?? [])
            ->map(fn (string $name): int => CandidateTag::query()->firstOrCreate(['name' => Str::lower($name)])->getKey())
            ->all();
    }
}
