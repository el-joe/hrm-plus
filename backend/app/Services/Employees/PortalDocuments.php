<?php

namespace App\Services\Employees;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeDocument;
use App\Models\HrRequestAttachment;
use App\Models\Payslip;
use App\Models\TrainingCertificate;
use App\Services\Payroll\PortalPayslips;
use App\Services\Training\PortalTraining;
use Illuminate\Database\Eloquent\Builder;

/**
 * "My documents" (portal page and API GET /api/v1/me/documents/all): one list of
 * every file the employee may open about themselves —
 *
 *  - documents HR shared (employee_documents.visible_to_employee),
 *  - their own employment contracts that have a signed copy,
 *  - their training certificates with a file,
 *  - letters issued on their HR requests (attachments flagged is_letter, never internal),
 *  - their issued payslips (PDF).
 *
 * Every query is bound to the employee id; downloads go through the owner-only routes.
 * Items are plain arrays: {id, type, title, subtitle, date, expiry_date, is_expired,
 * file_name, file_mime, file_size, ...}; the caller adds the download URL of its channel.
 */
class PortalDocuments
{
    public const TYPES = ['documents', 'contracts', 'certificates', 'letters', 'payslips'];

    public function __construct(
        private readonly PortalProfile $profile,
        private readonly PortalTraining $training,
        private readonly PortalPayslips $payslips,
    ) {}

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function all(Employee $employee): array
    {
        return [
            'documents' => $this->documents($employee),
            'contracts' => $this->contracts($employee),
            'certificates' => $this->certificates($employee),
            'letters' => $this->letters($employee),
            'payslips' => $this->payslipItems($employee),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function documents(Employee $employee): array
    {
        return $this->profile->documents($employee)->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn (EmployeeDocument $document): array => [
                'id' => $document->id,
                'type' => 'document',
                'category' => $document->category->value,
                'category_label' => $document->category->label(),
                'title' => $document->title,
                'subtitle' => $document->category->label(),
                'date' => ($document->issue_date ?? $document->created_at)?->toDateString(),
                'expiry_date' => $document->expiry_date?->toDateString(),
                'is_expired' => $document->isExpired(),
                ...$this->file($document->file_name, $document->file_mime, $document->file_size),
            ])->values()->all();
    }

    /**
     * @return Builder<EmployeeContract>
     */
    public function contractQuery(Employee $employee): Builder
    {
        return EmployeeContract::query()->withoutGlobalScopes()->where('employee_id', $employee->id)->whereNotNull('file_path');
    }

    public function findContract(Employee $employee, int $id): ?EmployeeContract
    {
        return $this->contractQuery($employee)->find($id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function contracts(Employee $employee): array
    {
        return $this->contractQuery($employee)->orderByDesc('start_date')->orderByDesc('id')->get()
            ->map(fn (EmployeeContract $contract): array => [
                'id' => $contract->id,
                'type' => 'contract',
                'title' => $contract->contract_type->label(),
                'subtitle' => $contract->reference,
                'reference' => $contract->reference,
                'date' => $contract->start_date?->toDateString(),
                'expiry_date' => $contract->effectiveEndDate()?->toDateString(),
                'is_current' => $contract->isCurrent(),
                'is_expired' => $contract->effectiveEndDate() !== null && $contract->effectiveEndDate()->lt(today()),
                ...$this->file($contract->file_name, $contract->file_mime, $contract->file_size),
            ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function certificates(Employee $employee): array
    {
        return $this->training->certificates($employee)->whereNotNull('file_path')->get()
            ->map(fn (TrainingCertificate $certificate): array => [
                'id' => $certificate->id,
                'type' => 'certificate',
                'title' => (string) ($certificate->name ?: $certificate->course?->name),
                'subtitle' => $certificate->issuer,
                'date' => $certificate->issued_on?->toDateString(),
                'expiry_date' => $certificate->expires_on?->toDateString(),
                'is_expired' => $certificate->isExpired(),
                ...$this->file($certificate->file_name, $certificate->file_mime, $certificate->file_size),
            ])->values()->all();
    }

    /**
     * @return Builder<HrRequestAttachment>
     */
    public function letterQuery(Employee $employee): Builder
    {
        return HrRequestAttachment::query()->where('is_letter', true)->where('is_internal', false)
            ->whereHas('hrRequest', fn (Builder $query) => $query->withoutGlobalScopes()->where('employee_id', $employee->id))
            ->with(['hrRequest' => fn ($query) => $query->withoutGlobalScopes()->with('category')]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function letters(Employee $employee): array
    {
        return $this->letterQuery($employee)->latest('id')->get()
            ->map(fn (HrRequestAttachment $letter): array => [
                'id' => $letter->id,
                'type' => 'letter',
                'title' => (string) ($letter->hrRequest?->category?->name ?? $letter->hrRequest?->subject),
                'subtitle' => $letter->hrRequest?->reference,
                'request_id' => $letter->hr_request_id,
                'request_reference' => $letter->hrRequest?->reference,
                'date' => $letter->created_at?->toDateString(),
                'expiry_date' => null,
                'is_expired' => false,
                ...$this->file($letter->file_name, $letter->file_mime, $letter->file_size),
            ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payslipItems(Employee $employee, int $limit = 24): array
    {
        return $this->payslips->payslips($employee)->limit($limit)->get()
            ->map(fn (Payslip $payslip): array => [
                'id' => $payslip->id,
                'type' => 'payslip',
                'title' => $payslip->period->name,
                'subtitle' => $payslip->number,
                'date' => $payslip->period->pay_date?->toDateString(),
                'expiry_date' => null,
                'is_expired' => false,
                'file_name' => null,
                'file_mime' => 'application/pdf',
                'file_size' => null,
            ])->values()->all();
    }

    /**
     * @return array<string, int>
     */
    public static function counts(array $all): array
    {
        return array_map(fn (array $items): int => count($items), $all);
    }

    /**
     * @return array{file_name: ?string, file_mime: ?string, file_size: ?int}
     */
    private function file(?string $name, ?string $mime, ?int $size): array
    {
        return ['file_name' => $name, 'file_mime' => $mime, 'file_size' => $size];
    }
}
