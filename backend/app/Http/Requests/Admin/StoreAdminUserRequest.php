<?php

namespace App\Http\Requests\Admin;

use App\Models\User;

class StoreAdminUserRequest extends AdminUserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    protected function target(): ?User
    {
        return null;
    }
}
