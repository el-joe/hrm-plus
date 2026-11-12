<?php

namespace App\Http\Requests\Employee;

use App\Enums\CheckInChannel;
use App\Enums\PunchDirection;
use App\Http\Requests\Api\V1\MobileAttendanceRequest;
use App\Services\Attendance\Mobile\CheckInData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /portal/attendance/check-in | check-out (JSON, session + CSRF).
 *
 *   request_uuid         uuid per tap (reused on a retry after a network error: idempotent)
 *   latitude, longitude  browser Geolocation API (optional: the server rejects a missing
 *   accuracy             position unless the policy lets web check-ins skip the geofence)
 *   device_time          ISO-8601 with offset, the computer's clock
 *   face                 {images: [base64 JPEG]} from the camera, or {token} from a provider web SDK
 *
 * The platform is always "web" and the device is the browser's user agent;
 * a browser cannot report a mock location or liveness, so neither is accepted.
 */
class PortalCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->employee_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'request_uuid' => ['required', 'uuid'],
            'latitude' => ['nullable', 'required_with:longitude,accuracy', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude,accuracy', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'required_with:latitude,longitude', 'numeric', 'min:0', 'max:100000'],
            'device_time' => ['nullable', 'date'],
            'face' => ['nullable', 'array'],
            'face.token' => ['nullable', 'string', 'max:4096'],
            'face.images' => ['nullable', 'array', 'max:'.config('face_verification.max_images')],
            'face.images.*' => ['string', 'max:'.(int) (config('face_verification.max_image_kb') * 1400)],
        ];
    }

    public function toData(PunchDirection $direction): CheckInData
    {
        $hasPosition = $this->filled('latitude') && $this->filled('longitude') && $this->filled('accuracy');
        $face = $this->input('face');

        return new CheckInData(
            $this->string('request_uuid')->lower()->value(),
            $direction,
            $hasPosition ? (float) $this->input('latitude') : null,
            $hasPosition ? (float) $this->input('longitude') : null,
            $hasPosition ? (float) $this->input('accuracy') : null,
            false,
            $this->filled('device_time') ? CarbonImmutable::parse($this->input('device_time')) : null,
            mb_substr((string) $this->userAgent(), 0, 191) ?: null,
            'web',
            null,
            MobileAttendanceRequest::faceSample(is_array($face) ? array_intersect_key($face, array_flip(['token', 'images'])) : null),
            $this->ip(),
            CheckInChannel::Web,
        );
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('mobile.api.fields');
    }
}
