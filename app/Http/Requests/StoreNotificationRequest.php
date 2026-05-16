<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'userId' => ['nullable', 'integer', 'exists:users,id', 'required_without:role', 'prohibits:role'],
            'role' => ['nullable', 'string', Rule::in(['student', 'admin', 'officer']), 'required_without:userId', 'prohibits:userId'],
            'type' => ['required', 'string', Rule::in(['status', 'warning', 'success', 'insight', 'task', 'admin'])],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'payload' => ['nullable', 'array'],
        ];
    }
}
