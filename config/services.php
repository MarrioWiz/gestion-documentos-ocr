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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Si la ruta del .env no existe (o viene vacía), se busca el programa en
    // el PATH del sistema y en su carpeta de instalación típica, para que el
    // proyecto funcione en otra computadora sin tener que escribir rutas.
    'tesseract' => [
        'executable' => (function () {
            foreach ([env('TESSERACT_PATH'), 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe', '/usr/bin/tesseract', '/opt/homebrew/bin/tesseract'] as $ruta) {
                if ($ruta && is_file($ruta)) {
                    return $ruta;
                }
            }

            return (new \Symfony\Component\Process\ExecutableFinder)->find('tesseract', 'tesseract');
        })(),
        'tessdata_dir' => env('TESSERACT_TESSDATA_DIR') ?: storage_path('tessdata'),
    ],

    'mutool' => [
        'executable' => (function () {
            $configurado = env('MUTOOL_PATH');

            return $configurado && is_file($configurado)
                ? $configurado
                : (new \Symfony\Component\Process\ExecutableFinder)->find('mutool');
        })(),
    ],

];
