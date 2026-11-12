<?php

namespace App\Http\Controllers\Admin\HrRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HrRequests\HrLetterTemplateRequest;
use App\Models\HrLetterTemplate;
use App\Services\HrRequests\HrLetterDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Letter templates (hr_requests.manage): bilingual plain text with {{placeholders}}; the page of a
 * template previews both languages with sample data (never a real employee's).
 */
class HrLetterTemplateController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', HrLetterTemplate::class);

        return view('admin.hr-requests.letters.index', [
            'templates' => HrLetterTemplate::query()->withCount('categories')->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', HrLetterTemplate::class);

        return $this->form(new HrLetterTemplate(['is_active' => true]));
    }

    public function store(HrLetterTemplateRequest $request): RedirectResponse
    {
        $template = HrLetterTemplate::create($request->validated());

        return redirect()->route('admin.hr-letter-templates.show', $template)->with('status', __('hr_requests.letters.saved', ['name' => $template->name]));
    }

    public function show(HrLetterTemplate $hrLetterTemplate, HrLetterDocument $document): View
    {
        Gate::authorize('viewAny', HrLetterTemplate::class);

        return view('admin.hr-requests.letters.show', [
            'template' => $hrLetterTemplate,
            'previews' => collect(['ar', 'en'])->mapWithKeys(fn (string $locale): array => [
                $locale => $document->renderBody($hrLetterTemplate, $locale, $document->sampleValues($locale)),
            ]),
            'usesSalary' => $document->usesFinancialData($hrLetterTemplate),
        ]);
    }

    public function edit(HrLetterTemplate $hrLetterTemplate): View
    {
        Gate::authorize('update', $hrLetterTemplate);

        return $this->form($hrLetterTemplate);
    }

    public function update(HrLetterTemplateRequest $request, HrLetterTemplate $hrLetterTemplate): RedirectResponse
    {
        $hrLetterTemplate->update($request->validated());

        return redirect()->route('admin.hr-letter-templates.show', $hrLetterTemplate)->with('status', __('hr_requests.letters.saved', ['name' => $hrLetterTemplate->name]));
    }

    public function destroy(HrLetterTemplate $hrLetterTemplate): RedirectResponse
    {
        Gate::authorize('update', $hrLetterTemplate);

        if ($hrLetterTemplate->categories()->exists()) {
            throw ValidationException::withMessages(['record' => __('hr_requests.letters.in_use', ['name' => $hrLetterTemplate->name])]);
        }

        $hrLetterTemplate->delete();

        return redirect()->route('admin.hr-letter-templates.index')->with('status', __('structure.messages.deleted', ['name' => $hrLetterTemplate->name]));
    }

    private function form(HrLetterTemplate $template): View
    {
        return view('admin.hr-requests.letters.form', ['template' => $template, 'placeholders' => HrLetterDocument::PLACEHOLDERS]);
    }
}
