<?php

return [
    // Enable/disable SMS globally
    'enabled' => env('SMS_ENABLED', false),

    // Which driver to use: 'twilio' or 'aws_sns'
    'driver' => env('SMS_DRIVER', null),

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'), // E.164-capable Twilio number or messaging service SID
    ],

    'aws_sns' => [
        'key' => env('AWS_SNS_KEY', env('AWS_ACCESS_KEY_ID')),
        'secret' => env('AWS_SNS_SECRET', env('AWS_SECRET_ACCESS_KEY')),
        'region' => env('AWS_SNS_REGION', env('AWS_DEFAULT_REGION', 'eu-west-1')),
        'sender_id' => env('AWS_SNS_SENDER_ID', 'AtoZ'),
        'sms_type' => env('AWS_SNS_SMS_TYPE', 'Transactional'),
    ],
];