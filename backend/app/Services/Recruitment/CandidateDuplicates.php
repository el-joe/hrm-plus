<?php

namespace App\Services\Recruitment;

use App\Models\Candidate;
use Illuminate\Database\Eloquent\Collection;

/**
 * Duplicate detection: candidates with the same e-mail (case-insensitive) or
 * the same phone (last 10 digits). Searched across the whole company, not
 * only the admin's data scope, so a restricted recruiter cannot create a
 * second record for someone another branch already has.
 */
class CandidateDuplicates
{
    /**
     * @return Collection<int, Candidate>
     */
    public function find(?string $email, ?string $phone, ?Candidate $except = null): Collection
    {
        $email = $email !== null && trim($email) !== '' ? mb_strtolower(trim($email)) : null;
        $phone = Candidate::normalisePhone($phone);

        if ($email === null && $phone === null) {
            return new Collection;
        }

        return Candidate::query()->withoutGlobalScopes()->whereNull('deleted_at')
            ->where(function ($query) use ($email, $phone): void {
                if ($email !== null) {
                    $query->orWhere('email', $email);
                }

                if ($phone !== null) {
                    $query->orWhere('phone_normalized', $phone);
                }
            })
            ->when($except?->exists, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->limit(5)
            ->get();
    }
}
