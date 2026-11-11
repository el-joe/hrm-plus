<?php

namespace App\Http\Resources\V1;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Branch
 */
class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'name_translations' => ['ar' => $this->name_ar, 'en' => $this->name_en],
            'is_headquarters' => $this->is_headquarters,
            'phone' => $this->phone,
            'email' => $this->email,
            'country_code' => $this->country_code,
            'address' => $this->localized('address'),
            'timezone' => $this->timezone ?? setting('localisation.timezone'),
            'locations' => BranchLocationResource::collection($this->whenLoaded('locations')),
        ];
    }
}
