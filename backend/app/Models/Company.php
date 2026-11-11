<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The company profile. There is exactly one company (assumption A1); read it
 * with Company::current().
 */
#[Fillable([
    'name_ar', 'name_en', 'legal_name_ar', 'legal_name_en', 'commercial_registration_no', 'tax_number',
    'email', 'phone', 'website', 'country_code', 'address_ar', 'address_en', 'founded_on',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Auditable, HasFactory, HasLocalizedName;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'founded_on' => 'date',
        ];
    }

    /**
     * The stored profile, or an unsaved one named after the application until
     * an admin saves the profile for the first time.
     */
    public static function current(): self
    {
        return static::query()->oldest('id')->first()
            ?? new static(['name_ar' => config('app.name'), 'name_en' => config('app.name')]);
    }

    public function logoUrl(): ?string
    {
        if ($this->logo_path === null) {
            return null;
        }

        return route('company.logo', ['v' => $this->updated_at?->getTimestamp()]);
    }

    protected function hasCodeColumn(): bool
    {
        return false;
    }
}
