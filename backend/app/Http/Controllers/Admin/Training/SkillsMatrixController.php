<?php

namespace App\Http\Controllers\Admin\Training;

use App\Actions\Training\SetEmployeeSkill;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSkill;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The skills matrix: employees in scope (rows) × skills (columns) with levels 1–5,
 * filtered by department, skill category or one skill; levels set by hand with
 * training.update (courses raise them automatically on completion).
 */
class SkillsMatrixController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EmployeeSkill::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'string', 'max:50'],
            'skill_id' => ['nullable', 'integer'],
            'min_level' => ['nullable', 'integer', 'between:1,'.Skill::MAX_LEVEL],
        ]);

        $skills = Skill::query()->active()
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['skill_id'] ?? null, fn (Builder $query, int $skill) => $query->whereKey($skill))
            ->orderBy('category')->orderByName()->get();

        $employees = Employee::query()->employed()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->search($search))
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $department) => $query->where('department_id', $department))
            ->when(($filters['skill_id'] ?? null) && ($filters['min_level'] ?? null), fn (Builder $query) => $query->whereIn('id', EmployeeSkill::query()->withoutGlobalScopes()
                ->where('skill_id', $filters['skill_id'])->where('level', '>=', $filters['min_level'])->select('employee_id')))
            ->with('department')->orderBy('employee_number')->paginate(self::PER_PAGE)->withQueryString();

        $levels = EmployeeSkill::query()->whereIn('employee_id', $employees->pluck('id'))->whereIn('skill_id', $skills->pluck('id'))->get()
            ->groupBy('employee_id')->map(fn ($rows) => $rows->keyBy('skill_id'));

        return view('admin.training.skills-matrix', [
            'skills' => $skills,
            'employees' => $employees,
            'levels' => $levels,
            'filters' => $filters,
            'departments' => Department::query()->orderByName()->get(),
            'categories' => Skill::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'allSkills' => Skill::query()->active()->orderByName()->get(),
            'canUpdate' => $request->user()->can('create', EmployeeSkill::class),
        ]);
    }

    public function update(Request $request, SetEmployeeSkill $set): RedirectResponse
    {
        Gate::authorize('create', EmployeeSkill::class);

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'skill_id' => ['required', 'integer'],
            'level' => ['required', 'integer', 'between:0,'.Skill::MAX_LEVEL],
        ]);

        // Scoped: only employees the admin can see.
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $skill = Skill::query()->findOrFail($data['skill_id']);
        $set->handle($employee, $skill, (int) $data['level'], $request->user());

        return back()->with('status', __('training.skills_matrix.saved', ['employee' => $employee->label(), 'skill' => $skill->name]));
    }
}
