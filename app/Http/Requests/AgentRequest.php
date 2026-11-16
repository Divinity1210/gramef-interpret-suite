<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Helpers\RoleHelper;

class AgentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if (!RoleHelper::hasRole($this->user(), 'admin')) {
            $this->request->remove('restrict_job_notifications');
        }
    }

    public function rules()
    {
        return [
            'title' => ['required', Rule::in(array_keys(config('enums.titles')))],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            // Ensure email is unique across users; when updating, ignore the current agent's user_id
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore(optional($this->route('agent'))->user_id)
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:6', 'max:255'],
            'contact_number' => ['required', 'string', 'max:255'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'county' => ['required', 'string', 'max:255'],
            'postcode' => ['required', 'string', 'max:255'],
//            'interpreter_types' => ['array', 'cannot_be_community_and_qualified'],
            'interpreter_types.*' => ['sometimes', 'exists:roles,name'],
            'gender' => [Rule::in(array_keys(config('enums.genders')))],
            'skills' => ['array'],
            'skills.*' => ['sometimes', 'exists:skills,id'],
            'languages' => ['array'],
            'skype_details' => ['nullable', 'string', 'max:255'],
            'profile_picture' => ['sometimes', 'string', 'max:255'],
            'contact_method' => ['sometimes', 'array'],
            'contact_method.*' => ['sometimes', 'exists:contact_methods,id'],
            'restrict_job_notifications' => ['sometimes', 'boolean'],
            'can_provide_affidavit' => ['sometimes', 'boolean'],
            'can_provide_affirmation' => ['sometimes', 'boolean'],
        ];
    }

    public function messages()
    {
        return [
            'profile_picture.required' => 'You must upload a profile picture',
            'profile_picture.string' => 'You must upload a profile picture',
        ];
    }

    public function withValidator($validator)
    {
        $validator->sometimes(['interpreter_types', 'gender', 'skills', 'languages', 'contact_method'], 'required', function() {
            if (RoleHelper::hasAnyRole($this->user(), 'agent')) {
                return false;
            }

            return true;
        });



        $validator->addExtension('cannot_be_community_and_qualified', function($attribute, $value) {
            if (!in_array('qualified-interpreter', $value)) {
                return true;
            }

            if (!in_array('community-interpreter', $value)) {
                return true;
            }

            return false;
        });

        $validator->addReplacer('cannot_be_community_and_qualified', function () {
            return 'You cannot be both a qualified and community interpreter.';
        });
    }
}
