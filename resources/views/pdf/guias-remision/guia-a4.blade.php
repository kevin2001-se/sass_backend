<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        .header { display: table; width: 100%; margin-bottom: 16px; }
        .company { display: table-cell; width: 62%; vertical-align: top; }
        .box { display: table-cell; width: 38%; border: 1px solid #111; text-align: center; padding: 10px; vertical-align: middle; }
        .section { border: 1px solid #bbb; padding: 8px; margin-bottom: 8px; }
        .title { font-weight: bold; background: #eee; padding: 4px; margin: -8px -8px 8px -8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #999; padding: 5px; }
        th { background: #eee; }
        .grid { width: 100%; }
        .grid td { border: 0; padding: 2px 4px; }
        .right { text-align: right; }
        .qr { width: 110px; }
        .muted { color: #555; font-size: 10px; }
        .watermark { position: fixed; top: 42%; left: 12%; font-size: 72px; color: #c00; opacity: 0.15; transform: rotate(-25deg); }
    </style>
</head>
<body>
@if($anulada)
    <div class="watermark">ANULADA</div>
@endif
<div class="header">
    <div class="company">
        <h2>{{ $empresa['razon_social'] }}</h2>
        <div>RUC: {{ $empresa['ruc'] }}</div>
        <div>{{ $empresa['direccion'] }}</div>
        @if(!empty($empresa['nombre_comercial']))<div>{{ $empresa['nombre_comercial'] }}</div>@endif
    </div>
    <div class="box">
        <h3>GUIA DE REMISION REMITENTE ELECTRONICA</h3>
        <h2>{{ $guia->numero_completo ?: $guia->numero_guia }}</h2>
    </div>
</div>

<div class="section">
    <div class="title">Datos de traslado</div>
    <table class="grid">
        <tr><td><strong>Fecha emision:</strong> {{ $guia->fecha_emision?->format('Y-m-d') }}</td><td><strong>Fecha traslado:</strong> {{ $guia->fecha_traslado?->format('Y-m-d') }}</td></tr>
        <tr><td><strong>Motivo:</strong> {{ $guia->motivo_traslado_codigo }} - {{ $guia->motivo_traslado_descripcion }}</td><td><strong>Modalidad:</strong> {{ $guia->modalidad_transporte }} {{ $guia->modalidadTransporte?->descripcion }}</td></tr>
        <tr><td><strong>Peso:</strong> {{ $guia->peso_total }} {{ $guia->unidad_peso }}</td><td><strong>Bultos:</strong> {{ $guia->numero_bultos ?: '-' }}</td></tr>
        <tr><td colspan="2"><strong>Referencia:</strong> {{ $guia->referencia_serie && $guia->referencia_numero ? $guia->referencia_serie.'-'.$guia->referencia_numero : '-' }}</td></tr>
    </table>
</div>

<div class="section">
    <div class="title">Destinatario y puntos</div>
    <p><strong>Destinatario:</strong> {{ $destinatario['nombre'] }} - {{ $destinatario['tipo_documento'] }} {{ $destinatario['numero_documento'] }}</p>
    <p><strong>Partida:</strong> {{ $guia->punto_partida_ubigeo }} - {{ $guia->punto_partida_direccion }}</p>
    <p><strong>Llegada:</strong> {{ $guia->punto_llegada_ubigeo }} - {{ $guia->punto_llegada_direccion }}</p>
</div>

<div class="section">
    <div class="title">Transporte</div>
    @if($guia->modalidad_transporte === '01')
        <p><strong>Transportista:</strong> {{ $guia->transportista_razon_social }} - RUC {{ $guia->transportista_ruc ?: $guia->transportista_numero_documento }}</p>
    @else
        <p><strong>Conductor:</strong> {{ $guia->conductor_nombre }} - {{ $guia->conductor_tipo_documento }} {{ $guia->conductor_numero_documento }}</p>
        <p><strong>Licencia:</strong> {{ $guia->conductor_licencia }} | <strong>Placa:</strong> {{ $guia->vehiculo_placa }}</p>
    @endif
</div>

<div class="section">
    <div class="title">Detalle de productos</div>
    <table>
        <thead>
        <tr><th>#</th><th>Descripcion</th><th>Unidad</th><th class="right">Cantidad</th><th class="right">Peso</th></tr>
        </thead>
        <tbody>
        @foreach($detalles as $detalle)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $detalle->descripcion }}</td>
                <td>{{ $detalle->unidad_medida }}</td>
                <td class="right">{{ number_format((float) $detalle->cantidad, 4) }}</td>
                <td class="right">{{ $detalle->peso !== null ? number_format((float) $detalle->peso, 3) : '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<table class="grid">
    <tr>
        <td style="width: 70%; vertical-align: top;">
            <p><strong>Observacion:</strong> {{ $guia->observacion ?: '-' }}</p>
            <p><strong>Estado guia:</strong> {{ $guia->estado }} | <strong>Estado SUNAT:</strong> {{ $guia->estado_sunat ?: 'PENDIENTE' }}</p>
            <p><strong>Hash:</strong> <span class="muted">{{ $guia->hash ?: '-' }}</span></p>
        </td>
        <td class="right" style="width: 30%;">
            <img class="qr" src="{{ $qr }}" alt="QR">
        </td>
    </tr>
</table>
</body>
</html>