<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MobileAttendanceRequest;
use App\Models\Employee;
use App\Services\Attendance\Face\FaceEnrolmentService;
use App\Services\Attendance\Face\FaceProviderException;
use App\Services\Employees\PortalProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * GET    /api/v1/me/face            consent + enrolment status, provider (is_simulated!)
 * POST   /api/v1/me/face/consent    {accepted: true, version}: give consent to the current text version
 * DELETE /api/v1/me/face/consent    withdraw consent: biometric data deleted at once
 * POST   /api/v1/me/face/enrol      {token?, images?: [base64], liveness_passed?}: enrol (replaces an earlier one)
 *
 * Own record only. Nothing biometric is ever returned.
 */
class FaceController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly FaceEnrolmentService $faces) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->faces->status($this->employee($request)));
    }

    public function consent(Request $request): JsonResponse
    {
        $request->validate([
            'accepted' => ['required', 'accepted'],
            'version' => ['required', 'string', 'in:'.$this->faces->currentConsentVersion()],
        ], ['version.in' => __('face.api.outdated_consent')]);

        $employee = $this->employee($request);
        $this->faces->giveConsent($employee, 'api', $request->user(), $request->ip());

        return ApiResponse::success($this->faces->status($employee), __('face.api.consent_given'), 201);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $this->faces->withdrawConsent($employee, $request->user());

        return ApiResponse::success($this->faces->status($employee), __('face.api.consent_withdrawn'));
    }

    public function enrol(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['nullable', 'string', 'max:4096', 'required_without:images'],
            'images' => ['nullable', 'array', 'max:'.config('face_verification.max_images')],
            'images.*' => ['string', 'max:'.(int) (config('face_verification.max_image_kb') * 1400)],
            'liveness_passed' => ['nullable', 'boolean'],
            'liveness_score' => ['nullable', 'numeric', 'between:0,1'],
        ]);

        $employee = $this->employee($request);

        try {
            $this->faces->enrol($employee, MobileAttendanceRequest::faceSample($request->only(['token', 'images', 'liveness_passed', 'liveness_score'])), $request->user());
        } catch (FaceProviderException $exception) {
            throw ValidationException::withMessages(['face' => __('face.api.errors.'.(trans()->has('face.api.errors.'.$exception->reason) ? $exception->reason : 'provider_unavailable'))]);
        }

        return ApiResponse::success($this->faces->status($employee), __('face.api.enrolled'), 201);
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
