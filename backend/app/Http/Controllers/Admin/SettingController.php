<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings.edit', $this->settings->groups()[0]);
    }

    public function edit(string $group): View
    {
        return view('admin.settings.edit', [
            'group' => $group,
            'groups' => $this->settings->groups(),
            'definitions' => $this->settings->groupDefinitions($group),
            'values' => $this->settings->group($group),
        ]);
    }

    public function update(UpdateSettingsRequest $request, string $group): RedirectResponse
    {
        $this->settings->setMany($request->settings());

        return redirect()->route('admin.settings.edit', $group)->with('status', __('app.settings.saved'));
    }
}
