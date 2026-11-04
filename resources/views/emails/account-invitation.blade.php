<x-mail::message>
# Hola, {{ $name }}

Tu solicitud en **{{ config('promas.name') }}** fue aprobada y ya tienes una cuenta para consultar tu crédito, tus pagos y tus documentos.

Crea tu contraseña con el siguiente botón. El enlace es personal y vence en {{ $hours }} horas.

<x-mail::button :url="$url">
Crear mi contraseña
</x-mail::button>

Si el enlace vence, entra a la página de inicio de sesión y elige "¿Olvidaste tu contraseña?".

Gracias,<br>
{{ config('promas.name') }}
</x-mail::message>
