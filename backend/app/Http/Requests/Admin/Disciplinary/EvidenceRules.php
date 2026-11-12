<?php

namespace App\Http\Requests\Admin\Disciplinary;

/**
 * Upload rules of disciplinary evidence (settings documents.*).
 */
trait EvidenceRules
{
    /**
     * @return array<string, list<string>>
     */
    protected function evidenceRules(bool $required = false): array
    {
        $extensions = implode(',', (array) setting('documents.allowed_extensions'));

        return [
            'evidence' => [$required ? 'required' : 'nullable', 'array', 'max:10'],
            'evidence.*' => ['file', 'mimes:'.$extensions, 'extensions:'.$extensions, 'max:'.(int) setting('documents.max_upload_size_kb')],
        ];
    }
}
