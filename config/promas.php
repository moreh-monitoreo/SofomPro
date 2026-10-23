<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Datos de la empresa
    |--------------------------------------------------------------------------
    |
    | Se muestran en el aviso de privacidad, en los recibos de pago y en los correos.
    | Los datos legales deben capturarse en las variables de entorno.
    |
    */

    'name' => env('COMPANY_NAME', 'PROMÁS+'),
    'legal_name' => env('COMPANY_LEGAL_NAME'),
    'rfc' => env('COMPANY_RFC'),
    'address' => env('COMPANY_ADDRESS'),
    'phone' => env('COMPANY_PHONE'),
    'email' => env('COMPANY_EMAIL'),
    'privacy_email' => env('COMPANY_PRIVACY_EMAIL', env('COMPANY_EMAIL')),

    // Correo del equipo que recibe las citas y avisos del sitio
    'notification_email' => env('CONTACT_NOTIFICATION_EMAIL', env('COMPANY_EMAIL')),

    // Versión del texto de consentimiento; se guarda junto con la fecha en que cada cliente lo aceptó
    'consent_version' => '2026-10',

    // Días de gracia después del vencimiento antes de considerar una cuota en mora
    'overdue_grace_days' => (int) env('OVERDUE_GRACE_DAYS', 0),

    // Verificación en dos pasos obligatoria para las cuentas del equipo (Administrator y Advisor)
    'two_factor_required' => (bool) env('TWO_FACTOR_REQUIRED', true),

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret' => env('RECAPTCHA_SECRET_KEY'),
    ],
];
