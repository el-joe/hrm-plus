<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dashboard\DashboardPreferenceRequest;
use App\Models\DashboardPreference;
use App\Services\Audit\AuditLogger;
use App\Services\Dashboard\WidgetRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Customise dashboard": show / hide and order the widgets the signed-in admin may see. Stored per
 * admin in dashboard_preferences; permissions still decide what can appear.
 */
class DashboardPreferenceController extends Controller
{
    public function edit(Request $request, WidgetRegistry $registry): View
    {
        return view('admin.dashboard-customise', [
            'widgets' => $registry->available($request->user()),
            'hidden' => $registry->preference($request->user())?->hidden ?? [],
        ]);
    }

    public function update(DashboardPreferenceRequest $request, WidgetRegistry $registry, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        if ($request->boolean('reset')) {
            DashboardPreference::query()->where('user_id', $user->id)->delete();

            return redirect()->route('admin.dashboard')->with('status', __('dashboard.customise.reset_done'));
        }

        $available = $registry->available($user);
        $positions = (array) $request->validated('position', []);
        $order = $available;
        usort($order, fn (string $a, string $b): int => [(int) ($positions[$a] ?? 999), array_search($a, $available, true)] <=> [(int) ($positions[$b] ?? 999), array_search($b, $available, true)]);
        $visible = (array) $request->validated('visible', []);
        $preference = $registry->save($user, $order, array_values(array_diff($available, $visible)));
        $audit->log('dashboard.customised', $preference, newValues: ['order' => $preference->order, 'hidden' => $preference->hidden]);

        return redirect()->route('admin.dashboard')->with('status', __('dashboard.customise.saved'));
    }
}
