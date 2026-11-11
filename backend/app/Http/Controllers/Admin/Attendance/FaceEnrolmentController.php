<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Enums\FaceEnrolmentStatus;
use App\Http\Controllers\Controller;
use App\Models\FaceConsent;
use App\Models\FaceEnrolment;
use App\Models\FaceVerification;
use App\Services\Attendance\Face\FaceEnrolmentService;
use App\Services\Attendance\Face\FaceProviderRegistry;
use App\Services\Employees\SensitiveDataAccessLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The face enrolment register: who consented (which version, when), who is
 * enrolled, with which provider (simulated is labelled), recent verification
 * results, and deletion of an employee's biometric data. Never shows a
 * template or an image; every view of the register is logged.
 */
class FaceEnrolmentController extends Controller
{
    public const ACCESS_EVENT = 'sensitive_access.face_enrolments';

    public function index(Request $request, FaceProviderRegistry $providers, SensitiveDataAccessLog $accessLog): View
    {
        Gate::authorize('viewAny', FaceEnrolment::class);

        $filters = $request->validate([
            'status' => ['nullable', 'in:active,deleted'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $enrolments = FaceEnrolment::query()->with(['employee', 'consent', 'deleter'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->whereHas('employee', fn (Builder $query) => $query->search($term)))
            ->latest('enrolled_at')->latest('id')
            ->paginate(25)->withQueryString();

        $accessLog->record(self::ACCESS_EVENT, null, ['enrolment_status', 'consent'], ['page' => $enrolments->currentPage(), 'rows' => $enrolments->count()], $request->user());

        $employeeIds = $enrolments->getCollection()->pluck('employee_id')->unique()->all();

        return view('admin.attendance.face.index', [
            'enrolments' => $enrolments,
            'filters' => $filters,
            'provider' => $providers->key(),
            'simulated' => $providers->isSimulated(),
            'consentVersion' => (string) setting('face_verification.consent_version'),
            'activeCount' => FaceEnrolment::query()->where('status', FaceEnrolmentStatus::Active)->count(),
            'consentCount' => FaceConsent::query()->whereNull('withdrawn_at')->whereHas('employee')->count(),
            'verifications' => FaceVerification::query()->whereIn('employee_id', $employeeIds)
                ->where('verified_at', '>=', now()->subDays(30))
                ->selectRaw('employee_id, result, count(*) as total')->groupBy('employee_id', 'result')->get()
                ->groupBy('employee_id'),
        ]);
    }

    public function destroy(Request $request, FaceEnrolment $enrolment, FaceEnrolmentService $faces): RedirectResponse
    {
        Gate::authorize('delete', $enrolment);

        $note = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        $faces->deleteEnrolment($enrolment, 'admin', $request->user(), note: $note);

        return back()->with('status', __('face.admin.deleted'));
    }
}
