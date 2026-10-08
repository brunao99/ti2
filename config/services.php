<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Envio de alertas. Enquanto o envio é simulado, a porcentagem abaixo
    // faz parte dos envios falhar de propósito, para testar o reenvio.
    'alertas' => [
        'simulacao_falha' => (int) env('ALERTA_SIMULACAO_FALHA', 0),
    ],

    // WhatsApp Cloud API (Meta). Com o driver "simulado" (padrão) nada sai
    // do servidor; com "meta" os alertas vão de verdade pelo template aprovado.
    'whatsapp' => [
        'driver'          => env('WHATSAPP_DRIVER', 'simulado'),
        'access_token'    => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'waba_id'         => env('WHATSAPP_WABA_ID'),
        'graph_version'   => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
        'verify_token'    => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret'      => env('WHATSAPP_APP_SECRET'),
        'template'        => env('WHATSAPP_TEMPLATE'),
        'template_lang'   => env('WHATSAPP_TEMPLATE_LANG', 'pt_BR'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
