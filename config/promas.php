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

    // Días antes del vencimiento en que se manda el recordatorio de pago al cliente (además del aviso el mismo día)
    'reminder_days_before' => (int) env('PAYMENT_REMINDER_DAYS_BEFORE', 3),

    // Las tareas diarias las dispara la primera visita al sitio después de esta hora (zona de APP_TIMEZONE),
    // sin necesidad de un cron en el servidor. Apagado en local para no mandar correos reales desde una máquina de desarrollo
    'daily_tasks_on_requests' => (bool) env('DAILY_TASKS_ON_REQUESTS', ! in_array(env('APP_ENV'), ['local', 'testing'], true)),
    'daily_tasks_at' => env('DAILY_TASKS_AT', '09:00'),

    // Verificación en dos pasos obligatoria para las cuentas del equipo (ver User::STAFF_ROLES)
    'two_factor_required' => (bool) env('TWO_FACTOR_REQUIRED', true),

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret' => env('RECAPTCHA_SECRET_KEY'),
    ],
];
