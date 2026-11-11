<?php

namespace App\Http\Requests\Admin;

use App\Models\User;

class UpdateAdminUserRequest extends AdminUserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->target());
    }

    protected function target(): ?User
    {
        return $this->route('admin_user');
    }
}
