<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCostAgreementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Adjust based on your authorization logic
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => 'required|exists:admins,id',
            'agent_id' => 'required|exists:agent_details,id',
            'visa_type' => 'nullable|string|max:255',
            'visa_subclass' => 'nullable|string|max:255',
            'visa_stream' => 'nullable|string|max:255',
            'professional_fee' => 'nullable|numeric|min:0',
            'additional_applicant_fee' => 'nullable|numeric|min:0',
            'gst' => 'nullable|numeric|min:0',
            'additional_clients' => 'nullable|array',
            'additional_clients.*.given_names' => 'nullable|string|max:255',
            'additional_clients.*.surname' => 'nullable|string|max:255',
            'additional_clients.*.dob' => 'nullable|date',
            'additional_clients.*.email' => 'nullable|email|max:255',
            'fees_and_charges' => 'nullable|array',
            'signed_date' => 'nullable|date',
            'client_declared' => 'boolean',
            'agent_declared' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'Please select a client.',
            'client_id.exists' => 'The selected client is invalid.',
            'agent_id.required' => 'Please select an agent.',
            'agent_id.exists' => 'The selected agent is invalid.',
            'professional_fee.numeric' => 'The professional fee must be a number.',
            'professional_fee.min' => 'The professional fee cannot be negative.',
            'additional_applicant_fee.numeric' => 'The additional applicant fee must be a number.',
            'additional_applicant_fee.min' => 'The additional applicant fee cannot be negative.',
            'gst.numeric' => 'The GST must be a number.',
            'gst.min' => 'The GST cannot be negative.',
            'additional_clients.*.dob.date' => 'The date of birth must be a valid date.',
            'additional_clients.*.email.email' => 'The email must be a valid email address.',
            'signed_date.date' => 'The signed date must be a valid date.',
        ];
    }
} 