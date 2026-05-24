<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        .header { width: 100%; border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 12px; }
        .company { width: 58%; display: inline-block; vertical-align: top; }
        .box { width: 38%; display: inline-block; vertical-align: top; border: 1px solid #111827; text-align: center; padding: 10px; }
        .title { font-size: 17px; font-weight: bold; margin-bottom: 6px; }
        .muted { color: #4b5563; }
        .section { border: 1px solid #d1d5db; padding: 8px; margin-bottom: 10px; }
        .section-title { font-weight: bold; margin-bottom: 6px; color: #312e81; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px; }
        th { background: #eef2ff; text-align: left; }
        .right { text-align: right; }
        .center { text-align: center; }
        .totals { width: 42%; margin-left: auto; }
        .qr { width: 105px; }
        .stamp { position: fixed; top: 330px; left: 90px; font-size: 72px; color: rgba(220, 38, 38, .18); transform: rotate(-25deg); font-weight: bold; }
    </style>
</head>
<body>
@if($anulada)
    <div class="stamp">ANULADA</div>
@endif

<div class="header">
    <div class="company">
        <div class="title">{{ $empresa['razon_social'] }}</div>
        <div><strong>RUC:</strong> {{ $empresa['ruc'] }}</div>
        <div><strong>Direcci&oacute;n:</strong> {{ $empresa['direccion'] }}</div>
        @if($empresa['nombre_comercial'])
            <div class="muted">{{ $empresa['nombre_comercial'] }}</div>
        @endif
    </div>
    <div class="box">
        <div class="title">NOTA DE CR&Eacute;DITO ELECTR&Oacute;NICA</div>
        <div>{{ $nota->numero_completo }}</div>
        <div class="muted">Estado SUNAT: {{ $nota->estado_sunat ?: 'PENDIENTE' }}</div>
    </div>
</div>

<div class="section">
    <div class="section-title">Datos del cliente</div>
    <table>
        <tr>
            <td><strong>Cliente:</strong> {{ $cliente['nombre'] }}</td>
            <td><strong>Documento:</strong> {{ $cliente['tipo_documento'] }} {{ $cliente['numero_documento'] }}</td>
            <td><strong>Fecha:</strong> {{ $nota->created_at?->format('d/m/Y') }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Comprobante relacionado</div>
    <table>
        <tr>
            <td><strong>Tipo:</strong> {{ $comprobante?->tipo_comprobante }}</td>
            <td><strong>N&uacute;mero:</strong> {{ $comprobante?->numero_comprobante }}</td>
            <td><strong>Motivo:</strong> {{ $nota->motivo_codigo }} - {{ $nota->motivo_descripcion ?: $motivo?->descripcion }}</td>
        </tr>
    </table>
</div>

<table>
    <thead>
    <tr>
        <th style="width: 8%">Cant.</th>
        <th style="width: 10%">Unidad</th>
        <th>Descripci&oacute;n</th>
        <th class="right" style="width: 14%">P. Unit.</th>
        <th class="right" style="width: 12%">Desc.</th>
        <th class="right" style="width: 14%">Total</th>
    </tr>
    </thead>
    <tbody>
    @foreach($detalles as $detalle)
        <tr>
            <td class="right">{{ number_format((float) $detalle->cantidad, 4) }}</td>
            <td>{{ $detalle->unidad_medida }}</td>
            <td>{{ $detalle->descripcion }}</td>
            <td class="right">S/ {{ number_format((float) $detalle->precio_unitario, 2) }}</td>
            <td class="right">S/ {{ number_format((float) $detalle->descuento, 2) }}</td>
            <td class="right">S/ {{ number_format((float) $detalle->total, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals" style="margin-top: 12px;">
    <tr><td>Subtotal</td><td class="right">S/ {{ number_format((float) $nota->subtotal, 2) }}</td></tr>
    <tr><td>Descuento</td><td class="right">S/ {{ number_format((float) $nota->total_descuento, 2) }}</td></tr>
    <tr><td>IGV</td><td class="right">S/ {{ number_format((float) $nota->total_igv, 2) }}</td></tr>
    <tr><th>Total</th><th class="right">S/ {{ number_format((float) $nota->total, 2) }}</th></tr>
</table>

<div class="section" style="margin-top: 14px;">
    <table>
        <tr>
            <td style="width: 120px;" class="center"><img class="qr" src="{{ $qr }}" alt="QR"></td>
            <td>
                <div><strong>Hash:</strong> {{ $nota->hash ?: '-' }}</div>
                <div><strong>C&oacute;digo respuesta:</strong> {{ $nota->codigo_respuesta ?: '-' }}</div>
                <div><strong>Mensaje SUNAT:</strong> {{ $nota->mensaje_respuesta ?: '-' }}</div>
                <div class="muted">Documento generado desde sistema interno. XML/CDR se descargan desde endpoints protegidos.</div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
