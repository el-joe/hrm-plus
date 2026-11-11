<?php

namespace App\Http\Controllers\Admin\Structure;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Http\Controllers\Controller;
use App\Models\Contracts\GuardsDeletion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Shared plumbing of the organisation-structure screens: list filters,
 * activate / deactivate and guarded deletion. Record rules live in the
 * policies (App\Policies\StructurePolicy) and the actions.
 */
abstract class StructureController extends Controller
{
    public const PER_PAGE = 20;

    /**
     * Validate the list filters: search, status (active | inactive) plus the given extra id filters.
     *
     * @param  list<string>  $idFilters
     * @return array<string, mixed>
     */
    protected function filters(Request $request, array $idFilters = []): array
    {
        $rules = [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ];

        foreach ($idFilters as $filter) {
            $rules[$filter] = ['nullable', 'integer'];
        }

        return $request->validate($rules);
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $idFilters  filters that match a column of the same name
     * @return Builder<*>
     */
    protected function applyFilters(Builder $query, array $filters, array $idFilters = []): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->search($search))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where(
                $query->qualifyColumn('is_active'), $status === 'active',
            ))
            ->where(function (Builder $query) use ($filters, $idFilters): void {
                foreach ($idFilters as $filter) {
                    if (filled($filters[$filter] ?? null)) {
                        $query->where($query->qualifyColumn($filter), $filters[$filter]);
                    }
                }
            });
    }

    /**
     * The shared create / edit page (admin/structure/form.blade.php) around the
     * entity's own fields partial (admin/structure/{resource}/_form.blade.php).
     *
     * @param  string  $resource  route resource name, e.g. "job-titles"
     * @param  string  $section  translation section under structure.*, e.g. "job_titles"
     * @param  array<string, mixed>  $data  extra view data; may override title, breadcrumbs, cancelUrl, formView
     * @param  list<Model>  $parents  parent route parameters of a nested resource
     */
    protected function formPage(Model $record, string $resource, string $section, array $data = [], array $parents = []): View
    {
        $indexUrl = $parents === [] ? route("admin.{$resource}.index") : null;

        return view('admin.structure.form', [
            'record' => $record,
            'formView' => "admin.structure.{$resource}._form",
            'title' => __("structure.{$section}.".($record->exists ? 'edit' : 'create')),
            'action' => $record->exists
                ? route("admin.{$resource}.update", [...$parents, $record])
                : route("admin.{$resource}.store", $parents),
            'cancelUrl' => $indexUrl,
            'breadcrumbs' => [['label' => __("structure.{$section}.title"), 'url' => $indexUrl]],
            'multipart' => false,
            ...$data,
        ]);
    }

    protected function updateStatus(Request $request, Model $record, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        Gate::authorize('update', $record);

        $active = $request->validate(['is_active' => ['required', 'boolean']])['is_active'];
        $changeStatus->handle($record, filter_var($active, FILTER_VALIDATE_BOOLEAN));

        return back()->with('status', __($record->is_active ? 'structure.messages.activated' : 'structure.messages.deactivated', ['name' => $record->name]));
    }

    protected function deleteRecord(Model&GuardsDeletion $record, DeleteStructureRecord $deleteRecord, string $indexRoute): RedirectResponse
    {
        Gate::authorize('delete', $record);

        $deleteRecord->handle($record);

        return redirect()->route($indexRoute)->with('status', __('structure.messages.deleted', ['name' => $record->name]));
    }
}
