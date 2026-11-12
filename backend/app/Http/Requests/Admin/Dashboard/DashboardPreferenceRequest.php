<?php

namespace App\Http\Requests\Admin\Dashboard;

use App\Services\Dashboard\WidgetRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Widget order (positions) and visibility of the signed-in admin's dashboard.
 *
 * position[{widget}] integer, visible[] widget keys that stay shown.
 */
class DashboardPreferenceRequest extends FormRequest
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
        $keys = array_keys(WidgetRegistry::WIDGETS);

        return [
            'position' => ['nullable', 'array'],
            'position.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'visible' => ['nullable', 'array'],
            'visible.*' => ['string', Rule::in($keys)],
            'reset' => ['nullable', 'boolean'],
        ];
    }
}
