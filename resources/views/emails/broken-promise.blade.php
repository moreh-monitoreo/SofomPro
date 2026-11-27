<x-mail::message>
# Promesa de pago incumplida

**{{ $customer }}** prometió pagar **${{ number_format($promise->promise_amount, 2) }}** a más tardar el **{{ $promise->promise_date->format('d/m/Y') }}** y desde entonces ha pagado **${{ number_format($paid, 2) }}**.

@if ($phone)
Teléfono: {{ $phone }}
@endif

Registra la siguiente gestión en el préstamo para darle seguimiento.

<x-mail::button :url="$url">
Ver el préstamo
</x-mail::button>

{{ config('promas.name') }}
</x-mail::message>
