<x-mail::message>
# Nueva solicitud de cita

Se registró una solicitud desde el sitio web.

<x-mail::table>
| Dato | Valor |
|:-----|:------|
| Nombre | {{ $contact->name }} |
| Correo | {{ $contact->email }} |
| Teléfono | {{ $contact->phone ?: 'No indicó' }} |
| Recibida | {{ $contact->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} |
</x-mail::table>

@if (filled($contact->message))
**Mensaje:**

<x-mail::panel>
{{ $contact->message }}
</x-mail::panel>
@endif

Puedes responder directamente a este correo para contestarle al cliente.
</x-mail::message>
