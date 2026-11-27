<x-mail::message>
# Hola, {{ $customer?->name }}

@if ($kind === 'vence')
**Hoy, {{ $due->translatedFormat('j \d\e F') }},** vence la cuota {{ $detail->number }} de tu préstamo.
@else
Te recordamos que la cuota {{ $detail->number }} de tu préstamo vence el **{{ $due->translatedFormat('j \d\e F') }}**.
@endif

Importe a pagar: **${{ number_format($amount, 2) }}**

Puedes pagar con tarjeta desde tu cuenta o por transferencia. Pagar a tiempo evita intereses moratorios.

<x-mail::button :url="$url">
Pagar desde mi cuenta
</x-mail::button>

Si ya realizaste tu pago, no es necesario que hagas nada.

{{ config('promas.name') }}
</x-mail::message>
