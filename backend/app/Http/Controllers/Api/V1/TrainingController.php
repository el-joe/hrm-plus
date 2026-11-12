<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Training\ManageTrainingCertificate;
use App\Actions\Training\ManageTrainingEnrolment;
use App\Enums\TrainingEnrolmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\OwnCertificateRequest;
use App\Http\Requests\Training\OwnTrainingRequest;
use App\Http\Resources\V1\TrainingCertificateResource;
use App\Http\Resources\V1\TrainingEnrolmentResource;
use App\Http\Resources\V1\TrainingSessionResource;
use App\Http\Resources\V1\TrainingSkillResource;
use App\Models\Employee;
use App\Models\TrainingEnrolment;
use App\Models\TrainingSession;
use App\Services\Employees\PortalProfile;
use App\Services\Training\PortalTraining;
use App\Services\Training\TrainingFileStore;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET  /api/v1/me/training                          summary: upcoming enrolments, pending requests, expiring certificates, skills
 * GET  /api/v1/me/training/enrolments?status=&page= own enrolments (paginated)
 * GET  /api/v1/me/training/enrolments/{id}          detail with attendance and approval history
 * POST /api/v1/me/training/enrolments/{id}/cancel   withdraw a pending request ({reason})
 * GET  /api/v1/me/training/sessions                 upcoming sessions open for a request (empty when requests are off)
 * POST /api/v1/me/training/requests                 request a place {training_session_id, reason} → 201 enrolment (pending)
 * GET  /api/v1/me/training/certificates             own certificates
 * POST /api/v1/me/training/certificates             upload (multipart: type, name, issuer, certificate_number, issued_on, expires_on, file)
 * GET  /api/v1/me/training/certificates/{id}/download  the file (own only)
 *
 * Always the token owner's own employee record.
 */
class TrainingController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalTraining $training) {}

    public function summary(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $days = (int) setting('training.certificate_reminder_days');
        $enrolments = $this->training->enrolments($employee)->get();

        return ApiResponse::success([
            'upcoming' => TrainingEnrolmentResource::collection($enrolments
                ->filter(fn (TrainingEnrolment $row): bool => $row->status === TrainingEnrolmentStatus::Enrolled && $row->session->end_date->gte(today()))
                ->sortBy(fn (TrainingEnrolment $row): string => $row->session->start_date->toDateString())->values())->resolve(),
            'pending_requests' => TrainingEnrolmentResource::collection($enrolments->where('status', TrainingEnrolmentStatus::Pending)->values())->resolve(),
            'completed_count' => $enrolments->where('status', TrainingEnrolmentStatus::Completed)->count(),
            'expiring_certificates' => TrainingCertificateResource::collection($this->training->certificates($employee)->expiringWithin($days)->get())->resolve(),
            'expired_certificates_count' => $this->training->certificates($employee)->expired()->count(),
            'skills' => TrainingSkillResource::collection($this->training->skills($employee))->resolve(),
        ], meta: [
            'requests_enabled' => (bool) setting('training.self_requests_enabled'),
            'certificate_reminder_days' => $days,
        ]);
    }

    public function enrolments(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::enum(TrainingEnrolmentStatus::class)]]);
        $query = $this->training->enrolments($this->employee($request))
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('training_enrolments.status', $status));

        return ApiResponse::paginated($query->paginate(20), TrainingEnrolmentResource::class);
    }

    public function enrolment(Request $request, int $enrolment): JsonResponse
    {
        return ApiResponse::success(new TrainingEnrolmentResource($this->findEnrolment($request, $enrolment)));
    }

    public function cancel(Request $request, int $enrolment, ManageTrainingEnrolment $enrolments): JsonResponse
    {
        $record = $this->findEnrolment($request, $enrolment);

        if (! $record->isPending()) {
            return ApiResponse::error(__('training.validation.not_cancellable'), 422, 'not_cancellable');
        }

        $enrolments->cancel($record, $request->user(), $request->validate(['reason' => ['nullable', 'string', 'max:1000']])['reason'] ?? null);

        return ApiResponse::success(new TrainingEnrolmentResource($this->findEnrolment($request, $enrolment)), __('training.portal.withdrawn'));
    }

    public function sessions(Request $request): JsonResponse
    {
        $sessions = setting('training.self_requests_enabled') ? $this->training->openSessions($this->employee($request)) : collect();

        return ApiResponse::success(TrainingSessionResource::collection($sessions)->resolve(), meta: [
            'requests_enabled' => (bool) setting('training.self_requests_enabled'),
        ]);
    }

    public function store(OwnTrainingRequest $request, ManageTrainingEnrolment $enrolments): JsonResponse
    {
        $employee = $this->employee($request);
        $session = TrainingSession::query()->find($request->integer('training_session_id'));
        abort_if($session === null, 404);

        $enrolment = $enrolments->request($session, $employee, $request->user(), $request->validated('reason'));

        return ApiResponse::success(new TrainingEnrolmentResource($this->findEnrolment($request, $enrolment->id)), __('training.portal.requested'), 201);
    }

    public function certificates(Request $request): JsonResponse
    {
        return ApiResponse::success(TrainingCertificateResource::collection($this->training->certificates($this->employee($request))->get())->resolve());
    }

    public function storeCertificate(OwnCertificateRequest $request, ManageTrainingCertificate $certificates): JsonResponse
    {
        $certificate = $certificates->store($this->employee($request), $request->certificateData(), $request->file('file'), $request->user());

        return ApiResponse::success(new TrainingCertificateResource($certificate->load('course')), __('training.portal.certificate_uploaded'), 201);
    }

    public function download(Request $request, int $certificate, TrainingFileStore $files): StreamedResponse
    {
        $record = $this->training->findCertificate($this->employee($request), $certificate);
        abort_if($record === null, 404);

        return $files->response($record);
    }

    private function findEnrolment(Request $request, int $id): TrainingEnrolment
    {
        $enrolment = $this->training->findEnrolment($this->employee($request), $id);
        abort_if($enrolment === null, 404);

        return $enrolment;
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
