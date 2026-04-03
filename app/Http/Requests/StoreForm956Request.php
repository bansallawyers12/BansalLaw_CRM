<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreForm956Request extends FormRequest
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
            'client_id' => 'required|exists:admins,id',
            'client_matter_id' => 'nullable|integer',
            'form956_folder_name' => 'nullable|integer|min:1',
            'agent_id' => 'required|exists:staff,id',
            'form_type' => 'required|in:appointment,withdrawal',

            // Application Details
            //'application_type' => 'required|in:visa,citizenship,sponsorship,nomination,other',
			'application_type' => 'required',
            'date_lodged' => 'nullable|date|required_if:not_lodged,0',
            'not_lodged' => 'nullable|boolean',

            // Part A fields
            'is_registered_migration_agent' => 'nullable|boolean',
            'is_legal_practitioner' => 'nullable|boolean',
            'is_exempt_person' => 'nullable|boolean',

            // Type of assistance fields
            'assistance_visa_application' => 'nullable|boolean',
            'assistance_sponsorship' => 'nullable|boolean',
            'assistance_nomination' => 'nullable|boolean',
            'assistance_cancellation' => 'nullable|boolean',
            'assistance_ministerial_intervention' => 'nullable|boolean',
            'assistance_other' => 'nullable|boolean',
            'assistance_other_details' => 'nullable|string|max:255|required_if:assistance_other,1',

            // Part A - Additional Questions
            'business_address' => 'nullable|string|max:255',
            'question_7' => 'nullable|boolean',
            'question_10' => 'nullable|boolean',
            'is_authorized_recipient' => 'nullable|boolean',

            // Part B fields
            'withdraw_authorized_recipient' => 'nullable|boolean',

            // Part C - Declarations
            'agent_declared' => 'nullable|boolean',
            'client_declared' => 'nullable|boolean',
            'agent_declaration_date' => 'nullable|date|required_if:agent_declared,1',
            'client_declaration_date' => 'nullable|date|required_if:client_declared,1',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'A client must be selected.',
            'client_id.exists' => 'The selected client does not exist.',
            'client_matter_id.integer' => 'The client matter ID must be a valid integer.',
            'agent_id.required' => 'An agent must be selected.',
            'agent_id.exists' => 'The selected agent does not exist.',
            'form_type.required' => 'The form type must be specified.',
            'form_type.in' => 'The form type must be either "appointment" or "withdrawal".',
            'application_type.required' => 'The type of application must be specified.',
            'application_type.in' => 'The type of application must be one of: visa, citizenship, sponsorship, nomination, or other.',
            'date_lodged.required_if' => 'The date lodged is required unless the application is not yet lodged.',
            'date_lodged.date' => 'The date lodged must be a valid date.',
            'not_lodged.boolean' => 'The "not lodged" field must be a boolean value.',
            'assistance_other_details.required_if' => 'Please specify the details for "Other" assistance type.',
            'assistance_other_details.max' => 'The "Other" assistance details cannot exceed 255 characters.',
            'business_address.max' => 'The business address cannot exceed 255 characters.',
            'agent_declaration_date.required_if' => 'The agent declaration date is required when the agent has declared.',
            'agent_declaration_date.date' => 'The agent declaration date must be a valid date.',
            'client_declaration_date.required_if' => 'The client declaration date is required when the client has declared.',
            'client_declaration_date.date' => 'The client declaration date must be a valid date.',
        ];
    }
}
