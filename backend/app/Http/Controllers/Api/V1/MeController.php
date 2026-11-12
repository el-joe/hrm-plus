<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateMyProfileRequest;
use App\Http\Resources\V1\EmployeeDocumentResource;
use App\Http\Resources\V1\EmployeeProfileResource;
use App\Http\Resources\V1\UserResource;
use App\Models\Employee;
use App\Services\Employees\EmployeeFileStore;
use App\Services\Employees\PortalDocuments;
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

    /**
     * Change what the employee may set directly: the language of server texts (labels, messages, e-mails, pushes).
     */
    public function updateProfile(UpdateMyProfileRequest $request): JsonResponse
    {
        $request->user()->update(['locale' => $request->validated('locale')]);
        app()->setLocale($request->validated('locale'));

        return ApiResponse::success(new UserResource($request->user()->refresh()), __('app.account.language_saved'));
    }

    /**
     * The employee's own photo (inline image); 404 when there is none.
     */
    public function photo(Request $request, EmployeeFileStore $files): StreamedResponse
    {
        $employee = $this->employee($request);
        abort_if($employee->photo_path === null, 404);
        $extension = strtolower(pathinfo($employee->photo_path, PATHINFO_EXTENSION));

        return $files->response($employee->photo_path, 'photo.'.$extension, 'image/'.($extension === 'jpg' ? 'jpeg' : $extension), inline: true);
    }

    /**
     * Every file about the employee in one answer: shared documents, contracts, training certificates,
     * letters issued on HR requests and payslips (latest 24), each with its authenticated download URL.
     */
    public function allDocuments(Request $request, PortalDocuments $documents): JsonResponse
    {
        $all = $documents->all($this->employee($request));
        $routes = [
            'documents' => fn (array $item): string => route('api.v1.me.documents.download', $item['id']),
            'contracts' => fn (array $item): string => route('api.v1.me.contracts.download', $item['id']),
            'certificates' => fn (array $item): string => route('api.v1.me.training.certificates.download', $item['id']),
            'letters' => fn (array $item): string => route('api.v1.me.requests.attachments.show', [$item['request_id'], $item['id']]),
            'payslips' => fn (array $item): string => route('api.v1.me.payslips.pdf', $item['id']),
        ];

        foreach ($all as $type => $items) {
            $all[$type] = array_map(fn (array $item): array => [...$item, 'download_url' => $routes[$type]($item)], $items);
        }

        return ApiResponse::success($all, meta: ['counts' => PortalDocuments::counts($all)]);
    }

    /**
     * The signed copy of one of the employee's own contracts (404 for any other id).
     */
    public function contract(Request $request, int $contract, PortalDocuments $documents, EmployeeFileStore $files, SensitiveDataAccessLog $log): StreamedResponse
    {
        $employee = $this->employee($request);
        $record = $documents->findContract($employee, $contract);
        abort_if($record === null, 404);
        $log->record(SensitiveDataAccessLog::CONTRACT, $record, ['file'], ['employee_id' => $employee->getKey(), 'own' => true, 'channel' => 'api'], $request->user());

        return $files->response($record->file_path, $record->file_name, $record->file_mime);
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
