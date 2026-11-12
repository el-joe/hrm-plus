<?php

namespace App\Http\Controllers\Admin\HrRequests;

use App\Actions\HrRequests\SaveHrRequestCategory;
use App\Enums\HrRequestCategoryType;
use App\Enums\HrRequestFieldType;
use App\Enums\HrRequestPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HrRequests\HrRequestCategoryRequest;
use App\Models\HrLetterTemplate;
use App\Models\HrRequestCategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Request categories (hr_requests.manage): type, SLA, default priority / assignee / escalation target,
 * approval and attachment rules, extra fields.
 */
class HrRequestCategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', HrRequestCategory::class);

        return view('admin.hr-requests.categories.index', [
            'categories' => HrRequestCategory::query()->with(['letterTemplate', 'defaultAssignee'])->withCount(['fields', 'requests' => fn ($query) => $query->withoutGlobalScopes()])
                ->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', HrRequestCategory::class);

        return $this->form(new HrRequestCategory(['type' => HrRequestCategoryType::General, 'default_priority' => HrRequestPriority::Normal, 'is_active' => true]));
    }

    public function store(HrRequestCategoryRequest $request, SaveHrRequestCategory $save): RedirectResponse
    {
        $category = $save->handle(new HrRequestCategory, $request->categoryData(), (array) $request->validated('fields', []));

        return redirect()->route('admin.hr-request-categories.index')->with('status', __('hr_requests.categories.saved', ['name' => $category->name]));
    }

    public function edit(HrRequestCategory $hrRequestCategory): View
    {
        Gate::authorize('update', $hrRequestCategory);

        return $this->form($hrRequestCategory->load('fields'));
    }

    public function update(HrRequestCategoryRequest $request, HrRequestCategory $hrRequestCategory, SaveHrRequestCategory $save): RedirectResponse
    {
        $save->handle($hrRequestCategory, $request->categoryData(), (array) $request->validated('fields', []));

        return redirect()->route('admin.hr-request-categories.index')->with('status', __('hr_requests.categories.saved', ['name' => $hrRequestCategory->name]));
    }

    public function destroy(HrRequestCategory $hrRequestCategory): RedirectResponse
    {
        Gate::authorize('update', $hrRequestCategory);

        if ($hrRequestCategory->requests()->withoutGlobalScopes()->exists()) {
            throw ValidationException::withMessages(['record' => __('hr_requests.categories.in_use', ['name' => $hrRequestCategory->name])]);
        }

        $hrRequestCategory->delete();

        return redirect()->route('admin.hr-request-categories.index')->with('status', __('structure.messages.deleted', ['name' => $hrRequestCategory->name]));
    }

    private function form(HrRequestCategory $category): View
    {
        return view('admin.hr-requests.categories.form', [
            'category' => $category,
            'types' => HrRequestCategoryType::cases(),
            'priorities' => HrRequestPriority::cases(),
            'fieldTypes' => HrRequestFieldType::cases(),
            'templates' => HrLetterTemplate::query()->activeOrKeep($category->hr_letter_template_id)->orderBy('code')->get(),
            'admins' => User::query()->admins()->orderBy('name')->get()->filter(fn (User $admin): bool => $admin->isActive() && $admin->can('hr_requests.update'))->values(),
        ]);
    }
}
