<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DashboardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'client_name' => 'nullable|string|max:255',
            'client_stage' => 'nullable|integer|exists:workflow_stages,id'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'client_name' => 'client name',
            'client_stage' => 'client stage'
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'client_name.string' => 'The client name must be a valid string.',
            'client_name.max' => 'The client name may not be greater than 255 characters.',
            'client_stage.integer' => 'The client stage must be a valid integer.',
            'client_stage.exists' => 'The selected client stage is invalid.'
        ];
    }
}
