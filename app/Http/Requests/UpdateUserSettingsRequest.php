<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'settings' => ['sometimes', 'array'],
            'settings.emailAlerts' => ['sometimes', 'boolean'],
            'settings.riskAlerts' => ['sometimes', 'boolean'],
            'settings.defaultRange' => ['sometimes', 'string', Rule::in(['Last 30 days', 'Last 3 months', 'Last 6 months'])],
            'settings.tableDensity' => ['sometimes', 'string', Rule::in(['Comfortable', 'Compact'])],
            'emailAlerts' => ['sometimes', 'boolean'],
            'riskAlerts' => ['sometimes', 'boolean'],
            'defaultRange' => ['sometimes', 'string', Rule::in(['Last 30 days', 'Last 3 months', 'Last 6 months'])],
            'tableDensity' => ['sometimes', 'string', Rule::in(['Comfortable', 'Compact'])],
        ];
    }
}
