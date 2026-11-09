<x-mail::message>
# Hola, {{ $name }}

Te dieron acceso al panel de **{{ config('promas.name') }}** con el rol **{{ $role }}**.

Crea tu contraseña con el siguiente botón. El enlace es personal y vence en {{ $hours }} horas. Al entrar por primera vez te pediremos activar la verificación en dos pasos con una aplicación como Google Authenticator.

<x-mail::button :url="$url">
Crear mi contraseña
</x-mail::button>

Si el enlace vence, pide al administrador que te envíe otro o entra a la página de inicio de sesión y elige "¿Olvidaste tu contraseña?".

Gracias,<br>
{{ config('promas.name') }}
</x-mail::message>
