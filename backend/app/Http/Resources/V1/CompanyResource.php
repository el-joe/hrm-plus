<?php

namespace App\Http\Resources\V1;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Company profile for the employee app. `name` / `legal_name` / `address`
 * follow the request locale; the *_translations objects carry both languages.
 * Localisation defaults come from the system settings.
 *
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'name_translations' => ['ar' => $this->name_ar, 'en' => $this->name_en],
            'legal_name' => $this->localized('legal_name'),
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'country_code' => $this->country_code,
            'address' => $this->localized('address'),
            'logo_url' => $this->logoUrl(),
            'currency_code' => setting('localisation.currency_code'),
            'currency_decimals' => setting('localisation.currency_decimals'),
            'timezone' => setting('localisation.timezone'),
            'date_format' => setting('localisation.date_format'),
            'time_format' => setting('localisation.time_format'),
            'first_day_of_week' => setting('localisation.first_day_of_week'),
        ];
    }
}
