<?php

namespace App\Http\Controllers\Admin\Training;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Training\SaveTrainingCourse;
use App\Enums\TrainingDeliveryMode;
use App\Http\Requests\Admin\Training\TrainingCourseRequest;
use App\Models\Skill;
use App\Models\TrainingCourse;
use App\Models\TrainingProgramme;
use App\Models\TrainingProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The course catalogue: provider, programme, delivery, duration, cost per participant,
 * certificate and validity, and the skills a course teaches.
 */
class TrainingCourseController extends TrainingCatalogueController
{
    protected function names(): array
    {
        return ['training-courses', 'courses'];
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TrainingCourse::class);

        $filters = $this->filters($request, ['training_programme_id', 'training_provider_id']);
        $records = $this->applyFilters(TrainingCourse::query()->with(['provider', 'programme', 'courseSkills.skill'])->withCount('sessions'), $filters, ['training_programme_id', 'training_provider_id'])
            ->orderByName()->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.training.training-courses.index', [
            'records' => $records,
            'filters' => $filters,
            'programmes' => TrainingProgramme::query()->orderByName()->get(),
            'providers' => TrainingProvider::query()->orderByName()->get(),
            'currency' => (string) setting('localisation.currency_code'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', TrainingCourse::class);

        return $this->catalogueForm(new TrainingCourse([
            'is_active' => true, 'delivery_mode' => TrainingDeliveryMode::Classroom, 'duration_hours' => '8', 'cost_per_participant' => '0', 'issues_certificate' => false,
        ]), $this->formData(null));
    }

    public function store(TrainingCourseRequest $request, SaveTrainingCourse $save): RedirectResponse
    {
        return $this->saved($save->handle(null, $request->recordData(), $request->skills()));
    }

    public function edit(TrainingCourse $trainingCourse): View
    {
        Gate::authorize('update', $trainingCourse);

        return $this->catalogueForm($trainingCourse->load('courseSkills'), $this->formData($trainingCourse));
    }

    public function update(TrainingCourseRequest $request, TrainingCourse $trainingCourse, SaveTrainingCourse $save): RedirectResponse
    {
        return $this->saved($save->handle($trainingCourse, $request->recordData(), $request->skills()));
    }

    public function destroy(TrainingCourse $trainingCourse): RedirectResponse
    {
        Gate::authorize('delete', $trainingCourse);
        $trainingCourse->courseSkills()->delete();

        return $this->destroyRecord($trainingCourse);
    }

    public function status(Request $request, TrainingCourse $trainingCourse, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->toggle($request, $trainingCourse, $changeStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?TrainingCourse $course): array
    {
        return [
            'modes' => TrainingDeliveryMode::cases(),
            'programmes' => TrainingProgramme::query()->activeOrKeep($course?->training_programme_id)->orderByName()->get(),
            'providers' => TrainingProvider::query()->activeOrKeep($course?->training_provider_id)->orderByName()->get(),
            'skills' => Skill::query()->where(fn (Builder $query) => $query->where('is_active', true)
                ->orWhereIn('id', $course?->courseSkills->pluck('skill_id') ?? []))->orderByName()->get(),
            'skillRows' => $course?->courseSkills->map(fn ($row): array => ['skill_id' => $row->skill_id, 'level' => $row->level])->all() ?? [],
        ];
    }
}
