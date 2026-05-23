<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrantBatchRequest extends FormRequest
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
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'programId' => ['required', 'integer', 'exists:scholarship_programs,id'],
            'programName' => ['nullable', 'string', 'max:255'],
            'semester' => ['required', 'string', 'max:255'],
            'schoolYear' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'claimingStartDate' => ['required', 'date'],
            'claimingEndDate' => ['required', 'date', 'after_or_equal:claimingStartDate'],
            'venue' => ['required', 'string', 'max:255'],
            'dailyLimit' => ['required', 'integer', 'min:1', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::in(['Draft', 'Open', 'Closed'])],
            'scholars' => ['required', 'array', 'min:1'],
            'scholars.*.id' => ['required', 'integer', 'distinct', 'exists:scholars,id'],
            'scholars.*.onHold' => ['nullable', 'boolean'],
        ];
    }
}
