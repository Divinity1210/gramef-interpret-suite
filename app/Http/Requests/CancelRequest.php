<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Helpers\RoleHelper;

class CancelRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'message' => ['string', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'message.required' => 'You must provide a reason for the cancellation',
        ];
    }

    public function withValidator($validator)
    {
        $validator->sometimes('message', 'required', function () {
            if (RoleHelper::hasRole($this->user(), 'admin')) {
                return false;
            }

            return true;
        });
    }
}
