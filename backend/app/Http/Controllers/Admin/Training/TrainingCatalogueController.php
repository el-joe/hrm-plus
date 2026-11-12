<?php

namespace App\Http\Controllers\Admin\Training;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Http\Controllers\Admin\Structure\StructureController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Shared plumbing of the training catalogue screens (providers, programmes, courses,
 * skills): the structure list filters and status switch, the shared form page, and
 * deletion of records nothing uses yet (records in use are deactivated instead — the
 * policy refuses deleting them).
 */
abstract class TrainingCatalogueController extends StructureController
{
    /**
     * Route resource name (e.g. "training-providers") and translation section under training.* (e.g. "providers").
     *
     * @return array{0: string, 1: string}
     */
    abstract protected function names(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function catalogueForm(Model $record, array $data = []): View
    {
        [$resource, $section] = $this->names();

        return $this->formPage($record, $resource, $section, [
            'title' => __("training.{$section}.".($record->exists ? 'edit' : 'create')),
            'breadcrumbs' => [['label' => __("training.{$section}.title"), 'url' => route("admin.{$resource}.index")]],
            'formView' => "admin.training.{$resource}._form",
            ...$data,
        ]);
    }

    protected function saved(Model $record): RedirectResponse
    {
        [$resource, $section] = $this->names();

        return redirect()->route("admin.{$resource}.index")->with('status', __('training.messages.saved', ['name' => $record->name]));
    }

    protected function destroyRecord(Model $record): RedirectResponse
    {
        Gate::authorize('delete', $record);
        [$resource] = $this->names();
        $record->delete();

        return redirect()->route("admin.{$resource}.index")->with('status', __('training.messages.deleted', ['name' => $record->name]));
    }

    protected function toggle(Request $request, Model $record, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $record, $changeStatus);
    }
}
