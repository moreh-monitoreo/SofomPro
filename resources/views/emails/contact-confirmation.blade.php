<x-mail::message>
# Hola, {{ $contact->name }}

Recibimos tu solicitud de cita en **{{ config('promas.name') }}**. Un asesor se pondrá en contacto contigo a la brevedad.

@if (filled($contact->message))
Este fue tu mensaje:

<x-mail::panel>
{{ $contact->message }}
</x-mail::panel>
@endif

Si necesitas algo antes, responde a este correo.

Gracias,<br>
{{ config('promas.name') }}
</x-mail::message>
