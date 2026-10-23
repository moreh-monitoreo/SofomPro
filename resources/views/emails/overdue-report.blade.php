<x-mail::message>
# Cartera vencida

Hay **{{ $rows->count() }}** cliente(s) con cuotas vencidas por un total de **${{ number_format($total, 2) }}**.

<x-mail::table>
| Cliente | Teléfono | Cuotas | Monto vencido | Días de atraso |
|:--------|:---------|:------:|--------------:|---------------:|
@foreach ($rows->take(25) as $row)
| {{ $row['customer'] }} | {{ $row['phone'] ?: '—' }} | {{ $row['overdue_installments'] }} | ${{ number_format($row['overdue_amount'], 2) }} | {{ $row['days_overdue'] }} |
@endforeach
</x-mail::table>

@if ($rows->count() > 25)
Se muestran los 25 con mayor atraso; el resto está en el reporte completo.
@endif

<x-mail::button :url="$url">
Ver cartera vencida
</x-mail::button>

{{ config('promas.name') }}
</x-mail::message>
