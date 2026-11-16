<?php

namespace App\Http\Requests;

use App\Rules\ValidAgentForJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Helpers\RoleHelper;

class InterpreterJobRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        //  dd($this->user());
        if (RoleHelper::hasRole($this->user(), 'client')) {
            $this->merge([
                'client_id' => $this->user()->client->id
            ]);
        }
    }

    public function rules()
    {

        $rules = [
            'to_language_id' => ['required', 'exists:languages,id'],
            'skill_id' => ['required', 'exists:skills,id'],
            'requested_agent_id' => ['nullable', 'exists:agents,id', new ValidAgentForJob('interpreter')],
            'require_qualified' => ['required', 'integer'],
           // 'require_security'=>['required','integer'],
            // 'interpreter_type_id'=>['required','integer'],
            'security_type_id' => ['required','integer'],
            'gender' => ['required', Rule::in(array_keys(config('enums.genders')))],
            'appointment_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i:s'],
//            'duration_hours' => ['required', 'integer', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'client_reference' => ['nullable', 'string', 'max:255'],
            'user_title' => ['nullable', Rule::in(array_keys(config('enums.titles')))],
            'user_first_name' => ['nullable', 'string', 'max:255'],
            'user_last_name' => ['nullable', 'string', 'max:255'],
            'personal_identity_number' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['required_if:skill_id, 1, 2', 'nullable', 'max:255'],
            'address_line_2' => ['nullable', 'max:255'],
            'county' => ['nullable', 'max:255'],
            'postcode' => ['required_if:skill_id, 1, 2', 'nullable', 'max:255'],
            'contact_information' => ['nullable', 'string', 'max:255'],
            'special_requirements' => ['nullable', 'string', 'max:255'],
            'file_reference' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'client_id' => ['sometimes', 'exists:clients,id'],
            'service_user_required' => ['sometimes', 'boolean'],
            'contact_information_is_same_as_account' => ['sometimes', 'boolean'],
            'bulk_id' => ['nullable', 'max:255'],
        ];

        if ($this->skill_id === "1") {
            $rules['duration_hours'] = ['required', 'integer', 'min:1'];
        } else {
            $rules['duration_hours'] = ['required', 'integer', 'min:0'];
        }

        return $rules;
    }
    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        if ($this->skill_id === "1") {
            $messages['duration_hours.min'] = 'The duration of a face to face meeting must be at least 1 hour';
        } else {
            $messages['duration_hours.min'] = 'The duration of a meeting must be more than 0 hours';
        }

        $messages['duration_minutes.integer'] = 'Minutes must be a whole number between 0 and 59';
        $messages['duration_minutes.min'] = 'Minutes must be at least 0';
        $messages['duration_minutes.max'] = 'Minutes may not be greater than 59';

        $messages['address_line_1.required_if'] = 'The address line 1 field is required when the service is Face To Face or BSL';
        $messages['postcode.required_if'] = 'The postcode field is required when the service is Face To Face or BSL';
        $messages['duration_hours.min'] = 'The duration of a face to face meeting must be at least 1 hour';

        return $messages;
    }




    public function withValidator($validator)
    {
        $validator->sometimes('contact_information', 'required', function () {
            if ((int) $this->skill_id !== 1) {
                return false;
            }

            if ((bool) $this->contact_information_is_same_as_account) {
                return false;
            }

            return true;
        });

        $validator->after(function ($v) {
            $hours = (int) ($this->input('duration_hours') ?? 0);
            $minutes = (int) ($this->input('duration_minutes') ?? 0);
            $totalMinutes = ($hours * 60) + $minutes;
            if ($totalMinutes <= 0) {
                $v->errors()->add('duration_hours', 'Appointment duration must be greater than 0 minutes');
                $v->errors()->add('duration_minutes', 'Appointment duration must be greater than 0 minutes');
            }
        });
    }
}
