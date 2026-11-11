<?php

namespace App\Actions\Recruitment;

use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Recruitment\ReferenceNumbers;
use Illuminate\Support\Facades\DB;

/**
 * Create or edit a vacancy. Validation (placement consistency, openings
 * within the position's free seats, salary only from permitted admins) is
 * done by App\Http\Requests\Admin\Recruitment\VacancyRequest.
 */
class SaveVacancy
{
    public function __construct(private readonly ReferenceNumbers $references) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Vacancy $vacancy, array $data, User $actor): Vacancy
    {
        return DB::transaction(function () use ($vacancy, $data, $actor): Vacancy {
            $vacancy ??= new Vacancy;

            if (blank($data['job_title_id'] ?? null) && filled($data['position_id'] ?? null)) {
                $data['job_title_id'] = Position::query()->withoutGlobalScopes()->whereKey($data['position_id'])->value('job_title_id');
            }

            $vacancy->fill($data);

            if (! $vacancy->exists) {
                $vacancy->forceFill([
                    'reference' => $this->references->next('vacancy'),
                    'created_by' => $actor->getKey(),
                    'currency' => $vacancy->currency ?: (string) setting('localisation.currency_code'),
                ]);
            }

            if ($vacancy->isDirty('status')) {
                $vacancy->closed_at = $vacancy->status->isClosed() ? now() : null;
            }

            $vacancy->updated_by = $actor->getKey();
            $vacancy->save();

            return $vacancy;
        });
    }
}
