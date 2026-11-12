<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Training\ManageTrainingCertificate;
use App\Actions\Training\ManageTrainingEnrolment;
use App\Enums\CertificateType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\OwnCertificateRequest;
use App\Http\Requests\Training\OwnTrainingRequest;
use App\Models\Employee;
use App\Models\TrainingSession;
use App\Services\Employees\PortalProfile;
use App\Services\Training\PortalTraining;
use App\Services\Training\TrainingFileStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "My training" (spec section 5, additional pages): my enrolments and attendance,
 * request a place on an upcoming session, my certificates (download, upload) and
 * my skills. Always the signed-in user's own records (another employee's id is a 404).
 */
class TrainingController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalTraining $training) {}

    public function index(Request $request): View
    {
        $employee = $this->employee($request);

        return view('employee.training.index', [
            'employee' => $employee,
            'enrolments' => $this->training->enrolments($employee)->paginate(10)->withQueryString(),
            'openSessions' => setting('training.self_requests_enabled') ? $this->training->openSessions($employee) : collect(),
            'requestsEnabled' => (bool) setting('training.self_requests_enabled'),
            'certificates' => $this->training->certificates($employee)->get(),
            'skills' => $this->training->skills($employee),
            'types' => CertificateType::cases(),
            'reminderDays' => (int) setting('training.certificate_reminder_days'),
        ]);
    }

    public function show(Request $request, int $enrolment): View
    {
        $record = $this->training->findEnrolment($this->employee($request), $enrolment);
        abort_if($record === null, 404);

        return view('employee.training.show', ['enrolment' => $record, 'days' => $record->session->days()]);
    }

    public function store(OwnTrainingRequest $request, ManageTrainingEnrolment $enrolments): RedirectResponse
    {
        $employee = $this->employee($request);
        $session = TrainingSession::query()->find($request->integer('training_session_id'));
        abort_if($session === null, 404);

        $enrolments->request($session, $employee, $request->user(), $request->validated('reason'));

        return redirect()->route('portal.training')->with('status', __('training.portal.requested'));
    }

    public function cancel(Request $request, int $enrolment, ManageTrainingEnrolment $enrolments): RedirectResponse
    {
        $record = $this->training->findEnrolment($this->employee($request), $enrolment);
        abort_if($record === null, 404);
        abort_unless($record->isPending(), 403);

        $enrolments->cancel($record, $request->user(), $request->validate(['reason' => ['nullable', 'string', 'max:1000']])['reason'] ?? null);

        return redirect()->route('portal.training')->with('status', __('training.portal.withdrawn'));
    }

    public function storeCertificate(OwnCertificateRequest $request, ManageTrainingCertificate $certificates): RedirectResponse
    {
        $certificates->store($this->employee($request), $request->certificateData(), $request->file('file'), $request->user());

        return redirect()->route('portal.training', ['#certificates'])->with('status', __('training.portal.certificate_uploaded'));
    }

    public function download(Request $request, int $certificate, TrainingFileStore $files): StreamedResponse
    {
        $record = $this->training->findCertificate($this->employee($request), $certificate);
        abort_if($record === null, 404);

        return $files->response($record);
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
