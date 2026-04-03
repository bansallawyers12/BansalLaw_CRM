<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'family_name' => 'required|string|max:255',
            'given_names' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'application_id' => 'nullable|string|max:255',
            'file_number' => 'nullable|string|max:255',
            'client_id' => 'nullable|string|max:255',
            'migration_status' => 'nullable|string|in:australian_citizen,permanent_resident,visa_holder,visa_applicant,other',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'fax' => 'nullable|string|max:20',
            'is_organization' => 'nullable|boolean',
            'organization_name' => 'nullable|string|max:255',
            'organization_position' => 'nullable|string|max:255',
            'organization_business_address' => 'nullable|string|max:255',
        ];
    }
}