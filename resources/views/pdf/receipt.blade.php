<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $number }}</title>
    <style>
        @page { margin: 36px 42px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #2d3133; }
        h1 { font-size: 20px; margin: 0; color: #ff5a0a; }
        h2 { font-size: 12px; margin: 22px 0 6px; text-transform: uppercase; letter-spacing: .5px; color: #555; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 6px 4px; vertical-align: top; }
        .muted { color: #777; }
        .right { text-align: right; }
        .header td { vertical-align: middle; }
        .box { border: 1px solid #ddd; border-radius: 4px; padding: 8px 12px; }
        .breakdown td { border-bottom: 1px solid #eee; }
        .total td { font-weight: bold; font-size: 13px; border-top: 2px solid #2d3133; border-bottom: none; }
        .footer { margin-top: 28px; font-size: 9px; color: #777; line-height: 1.5; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 60%">
                <h1>{{ $company['name'] }}</h1>
                @if ($company['legal_name'])<div><strong>{{ $company['legal_name'] }}</strong></div>@endif
                @if ($company['rfc'])<div>RFC: {{ $company['rfc'] }}</div>@endif
                @if ($company['address'])<div>{{ $company['address'] }}</div>@endif
                @if ($company['phone'] || $company['email'])
                    <div>{{ collect([$company['phone'], $company['email']])->filter()->implode(' · ') }}</div>
                @endif
            </td>
            <td class="right">
                <div class="muted">RECIBO DE PAGO</div>
                <div style="font-size: 16px; font-weight: bold;">{{ $number }}</div>
                <div class="muted">Emitido el {{ $issuedAt->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <h2>Datos del cliente</h2>
    <table>
        <tr>
            <td style="width: 50%"><span class="muted">Cliente</span><br><strong>{{ $customer->full_name }}</strong></td>
            <td><span class="muted">RFC</span><br>{{ $customer->rfc ?: '—' }}</td>
        </tr>
        <tr>
            <td><span class="muted">Crédito</span><br>{{ $folio }} · {{ $loan->type === 'FUNERARIO' ? 'Con garantía' : 'Personal' }}</td>
            <td><span class="muted">Monto del crédito</span><br>${{ number_format($loan->payment->request_amount ?? $loan->amount, 2) }}</td>
        </tr>
    </table>

    <h2>Pago registrado</h2>
    <table>
        <tr>
            <td style="width: 25%"><span class="muted">Cuota</span><br><strong>{{ $detail->number }} de {{ $installments }}</strong></td>
            <td style="width: 25%"><span class="muted">Fecha límite</span><br>{{ $detail->dueDate()->format('d/m/Y') }}</td>
            <td style="width: 25%"><span class="muted">Fecha de pago</span><br>{{ \Carbon\Carbon::parse($detail->payed_at)->format('d/m/Y H:i') }}</td>
            <td><span class="muted">Forma de pago</span><br>{{ $method }}</td>
        </tr>
        @if ($detail->payment_reference)
            <tr><td colspan="4"><span class="muted">Referencia</span><br>{{ $detail->payment_reference }}</td></tr>
        @endif
    </table>

    <h2>Desglose de la cuota</h2>
    <table class="breakdown">
        <tr><td>Capital</td><td class="right">${{ number_format($detail->principal_payment, 2) }}</td></tr>
        <tr><td>Interés ordinario</td><td class="right">${{ number_format($detail->interest_payment, 2) }}</td></tr>
        <tr><td>IVA sobre intereses</td><td class="right">${{ number_format($vat, 2) }}</td></tr>
        @if ($detail->moratory_paid > 0)
            <tr><td>Intereses moratorios</td><td class="right">${{ number_format($detail->moratory_paid, 2) }}</td></tr>
            <tr><td>IVA sobre intereses moratorios</td><td class="right">${{ number_format($detail->moratory_iva_paid, 2) }}</td></tr>
        @endif
        <tr class="total"><td>Total de la cuota</td><td class="right">${{ number_format($detail->monthly_payment, 2) }}</td></tr>
        @if ($detail->payment_total && round($detail->payment_total, 2) !== round($detail->monthly_payment, 2))
            <tr><td>Importe recibido</td><td class="right">${{ number_format($detail->payment_total, 2) }}</td></tr>
        @endif
    </table>

    <h2>Estado del crédito después de este pago</h2>
    <div class="box">
        <table>
            <tr>
                <td><span class="muted">Saldo de capital</span><br><strong>${{ number_format($detail->balance, 2) }}</strong></td>
                <td><span class="muted">Total pagado a la fecha</span><br><strong>${{ number_format($paidTotal, 2) }}</strong></td>
                <td><span class="muted">Cuotas pendientes (con intereses e IVA)</span><br><strong>${{ number_format($pendingTotal, 2) }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Este documento es un comprobante del pago de la cuota señalada y no sustituye al comprobante fiscal digital (CFDI).
        Conserve este recibo para cualquier aclaración. {{ $company['name'] }}@if ($company['email']) · {{ $company['email'] }}@endif
    </div>
</body>
</html>
