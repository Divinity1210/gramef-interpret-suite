<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Helpers\RoleHelper;

class AgentDocumentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            // File validation for documents
            'documents.*' => [
                'file',
                'max:20480',
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return;
                    }

                    $allowedExtensions = ['png', 'jpg', 'jpeg', 'doc', 'docx', 'pdf'];
                    $allowedMimes = [
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/octet-stream',
                    ];

                    $extension = strtolower((string) $value->getClientOriginalExtension());
                    $mime = (string) $value->getMimeType();

                    if (!in_array($extension, $allowedExtensions, true)) {
                        $fail('The ' . $attribute . ' must be a file of type: png, jpg, jpeg, doc, docx, pdf.');
                        return;
                    }

                    if (!in_array($mime, $allowedMimes, true)) {
                        $fail('The ' . $attribute . ' must be a file of type: png, jpg, jpeg, doc, docx, pdf.');
                        return;
                    }

                    if ($mime === 'application/octet-stream' && $extension !== 'pdf') {
                        $fail('The ' . $attribute . ' must be a file of type: png, jpg, jpeg, doc, docx, pdf.');
                        return;
                    }
                },
            ],
            'dbs_update_reference_number' => ['nullable', 'string', 'max:255'],
            'induction_date' => ['date'],
            'dbs_number' => ['nullable', 'string', 'max:255'],
            'dbs_level_id' => ['nullable', 'integer', 'between:1,3'],
        ];
    }

    public function withValidator($validator)
    {
        // Apply 'dbs_expiry_date' validation only for agents
        $validator->sometimes('dbs_expiry_date', ['nullable', 'date', 'after:today'], function () {
            return RoleHelper::hasRole($this->user(), 'agent'); // Only apply this rule for agents
        });

        // Ensure dbs_number is present but nullable
        $validator->sometimes(['dbs_expiry_date', 'dbs_number'], 'present|nullable', function ($input) {
            return true; // Always apply
        });

        // Make induction_date required for non-agents
        $validator->sometimes('induction_date', 'required', function () {
            return !RoleHelper::hasRole($this->user(), 'agent'); // Required for non-agents
        });
    }
}
