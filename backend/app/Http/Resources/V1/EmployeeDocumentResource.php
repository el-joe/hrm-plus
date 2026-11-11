<?php

namespace App\Http\Resources\V1;

use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A document shared with the employee. The file itself is fetched from download_url (authenticated).
 *
 * @mixin EmployeeDocument
 */
class EmployeeDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'title' => $this->title,
            'issue_date' => $this->issue_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'is_expired' => $this->isExpired(),
            'file_name' => $this->file_name,
            'file_mime' => $this->file_mime,
            'file_size' => $this->file_size,
            'uploaded_at' => $this->created_at?->toIso8601String(),
            'download_url' => route('api.v1.me.documents.download', $this->id),
        ];
    }
}
