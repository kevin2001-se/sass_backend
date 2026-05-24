<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111827; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #111827; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .qr { width: 85px; }
        .anulada { font-size: 22px; font-weight: bold; color: #b91c1c; border: 1px solid #b91c1c; padding: 4px; }
    </style>
</head>
<body>
<div class="center bold">{{ $empresa['razon_social'] }}</div>
<div class="center">RUC {{ $empresa['ruc'] }}</div>
<div class="center">{{ $empresa['direccion'] }}</div>
<div class="line"></div>
<div class="center bold">NOTA DE D&Eacute;BITO ELECTR&Oacute;NICA</div>
<div class="center bold">{{ $nota->numero_completo }}</div>
@if($anulada)
    <div class="center anulada">ANULADA</div>
@endif
<div>Fecha: {{ $nota->created_at?->format('d/m/Y H:i') }}</div>
<div>Estado SUNAT: {{ $nota->estado_sunat ?: 'PENDIENTE' }}</div>
<div class="line"></div>
<div>Doc. relacionado: {{ $comprobante?->numero_comprobante }}</div>
<div>Cliente: {{ $cliente['nombre'] }}</div>
<div>Doc.: {{ $cliente['tipo_documento'] }} {{ $cliente['numero_documento'] }}</div>
<div>Motivo: {{ $nota->motivo_codigo }} - {{ $nota->motivo_descripcion ?: $motivo?->descripcion }}</div>
<div class="line"></div>
<table>
    @foreach($detalles as $detalle)
        <tr>
            <td colspan="2">{{ $detalle->descripcion }}</td>
        </tr>
        <tr>
            <td>{{ number_format((float) $detalle->cantidad, 4) }} x S/ {{ number_format((float) $detalle->precio_unitario, 2) }}</td>
            <td class="right">S/ {{ number_format((float) $detalle->total, 2) }}</td>
        </tr>
    @endforeach
</table>
<div class="line"></div>
<table>
    <tr><td>Subtotal</td><td class="right">S/ {{ number_format((float) $nota->subtotal, 2) }}</td></tr>
    <tr><td>IGV</td><td class="right">S/ {{ number_format((float) $nota->total_igv, 2) }}</td></tr>
    <tr><td class="bold">TOTAL</td><td class="right bold">S/ {{ number_format((float) $nota->total, 2) }}</td></tr>
</table>
<div class="line"></div>
<div class="center"><img class="qr" src="{{ $qr }}" alt="QR"></div>
<div>Hash: {{ $nota->hash ?: '-' }}</div>
<div>CDR: {{ $nota->codigo_respuesta ?: '-' }}</div>
</body>
</html>