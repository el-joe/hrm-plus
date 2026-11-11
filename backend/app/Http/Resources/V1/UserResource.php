<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'account_type' => $this->account_type->value,
            'status' => $this->status->value,
            'locale' => $this->locale ?? config('app.locale'),
            'employee_id' => $this->employee_id,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
