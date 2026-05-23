<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        .header { display: table; width: 100%; margin-bottom: 18px; }
        .company { display: table-cell; width: 62%; vertical-align: top; }
        .box { display: table-cell; width: 38%; border: 1px solid #111; text-align: center; padding: 12px; vertical-align: middle; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #999; padding: 6px; }
        th { background: #eee; }
        .qr { width: 105px; }
    </style>
</head>
<body>
<div class="header">
    <div class="company">
        <h2>{{ $empresa['razon_social'] }}</h2>
        <div>RUC: {{ $empresa['ruc'] }}</div>
        <div>{{ $empresa['direccion'] }}</div>
    </div>
    <div class="box">
        <h2>GUIA DE REMISION ELECTRONICA</h2>
        <h3>{{ $comprobante->numero_comprobante }}</h3>
    </div>
</div>
<p><strong>Fecha emision:</strong> {{ $comprobante->fecha_emision->format('Y-m-d') }} | <strong>Fecha traslado:</strong> {{ $guia?->fecha_traslado?->format('Y-m-d') }}</p>
<p><strong>Motivo:</strong> {{ $guia?->motivo_traslado_codigo }} - {{ $guia?->motivo_traslado_descripcion }}</p>
<p><strong>Partida:</strong> {{ $guia?->punto_partida_ubigeo }} - {{ $guia?->punto_partida_direccion }}</p>
<p><strong>Llegada:</strong> {{ $guia?->punto_llegada_ubigeo }} - {{ $guia?->punto_llegada_direccion }}</p>
<p><strong>Destinatario:</strong> {{ $cliente['nombre'] }} - {{ $cliente['tipo_documento'] }} {{ $cliente['numero_documento'] }}</p>
<p><strong>Transporte:</strong> {{ $guia?->modalidad_transporte }} | <strong>Peso:</strong> {{ $guia?->peso_total }} {{ $guia?->unidad_peso }} | <strong>Bultos:</strong> {{ $guia?->numero_bultos }}</p>
<p><strong>Transportista:</strong> {{ $guia?->transportista_razon_social }} {{ $guia?->transportista_numero_documento }}</p>
<p><strong>Conductor:</strong> {{ $guia?->conductor_nombre }} {{ $guia?->conductor_numero_documento }} | <strong>Licencia:</strong> {{ $guia?->conductor_licencia }} | <strong>Placa:</strong> {{ $guia?->vehiculo_placa }}</p>
@include('pdf.sunat.partials.detalles-tabla')
@include('pdf.sunat.partials.sunat-footer')
</body>
</html>
