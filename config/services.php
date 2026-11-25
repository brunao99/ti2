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
        // Manda junto a imagem "Comunicado importante" (ImagemAlerta), com o
        // texto do alerta como legenda. Hoje só o envio pelo Twilio usa.
        'imagem' => (bool) env('ALERTA_IMAGEM', false),
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

    // WhatsApp pelo Twilio (WHATSAPP_DRIVER=twilio), usado na demonstração
    // com o sandbox. O número padrão é o do sandbox do Twilio.
    'twilio' => [
        'account_sid'   => env('TWILIO_ACCOUNT_SID'),
        'auth_token'    => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'),
        // Modelos do Content Template Builder (HX...). Com eles, o envio vai
        // por modelo, como a conta de teste exige; sem eles, vai texto livre.
        'content_sid' => env('TWILIO_CONTENT_SID'),
        'content_sid_imagem' => env('TWILIO_CONTENT_SID_IMAGEM'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
