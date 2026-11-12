<?php

namespace App\Http\Requests\Admin\Dashboard;

use App\Services\Dashboard\AdminDashboard;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared dashboard filter bar: date range, branch, department, employee group (query string).
 */
class DashboardFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...AdminDashboard::rules(), 'refresh' => ['nullable', 'boolean']];
    }
}
