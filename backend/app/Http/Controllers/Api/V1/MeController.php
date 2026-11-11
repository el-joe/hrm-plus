<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\EmployeeDocumentResource;
use App\Http\Resources\V1\EmployeeProfileResource;
use App\Models\Employee;
use App\Services\Employees\EmployeeFileStore;
use App\Services\Employees\PortalProfile;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Support\ApiListQuery;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The signed-in employee's own profile and documents. Everything is
 * resolved from the token owner's employee_id; there is no employee id in
 * the URL, so nobody can ask for someone else's data.
 */
class MeController extends Controller
{
    public function __construct(private readonly PortalProfile $profile) {}

    public function profile(Request $request): JsonResponse
    {
        return ApiResponse::success(new EmployeeProfileResource($this->employee($request)));
    }

    public function documents(Request $request): JsonResponse
    {
        $documents = ApiListQuery::for($this->profile->documents($this->employee($request)), $request)
            ->allowFilters([
                'category',
                'expired' => fn (Builder $query, string $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    ? $query->whereDate('expiry_date', '<', today())
                    : $query->where(fn (Builder $query) => $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today())),
            ])
            ->allowSorts(['created_at', 'expiry_date', 'title'])
            ->defaultSort('-created_at')
            ->paginate();

        return ApiResponse::paginated($documents, EmployeeDocumentResource::class);
    }

    public function document(Request $request, int $document): JsonResponse
    {
        return ApiResponse::success(new EmployeeDocumentResource($this->profile->documents($this->employee($request))->findOrFail($document)));
    }

    public function download(Request $request, int $document, EmployeeFileStore $files, SensitiveDataAccessLog $log): StreamedResponse
    {
        $employee = $this->employee($request);
        $record = $this->profile->documents($employee)->findOrFail($document);
        $log->record(SensitiveDataAccessLog::DOCUMENT, $record, ['file'], ['employee_id' => $employee->getKey(), 'own' => true, 'channel' => 'api'], $request->user());

        return $files->response($record->file_path, $record->file_name, $record->file_mime);
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
