<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReleaseGrantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isOfficer() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $beneficiary = $this->route('grantBeneficiary');

        return [
            'referenceNumber' => [
                'required',
                'string',
                'max:255',
                Rule::unique('grant_beneficiaries', 'reference_number')->ignore($beneficiary?->id),
            ],
            'claimMethod' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
