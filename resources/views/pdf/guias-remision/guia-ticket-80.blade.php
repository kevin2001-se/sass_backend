<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; width: 80mm; margin: 0; color: #000; }
        .center { text-align: center; }
        .line { border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .right { text-align: right; }
        .qr { width: 85px; }
        .bold { font-weight: bold; }
    </style>
</head>
<body>
<div class="center">
    <div class="bold">{{ $empresa['razon_social'] }}</div>
    <div>RUC {{ $empresa['ruc'] }}</div>
    <div>{{ $empresa['direccion'] }}</div>
    <div class="line"></div>
    <div class="bold">GUIA DE REMISION REMITENTE</div>
    <div class="bold">{{ $guia->numero_completo ?: $guia->numero_guia }}</div>
</div>
<div class="line"></div>
<div>F. Emision: {{ $guia->fecha_emision?->format('Y-m-d') }}</div>
<div>F. Traslado: {{ $guia->fecha_traslado?->format('Y-m-d') }}</div>
<div>Estado: {{ $guia->estado }} / SUNAT: {{ $guia->estado_sunat ?: 'PENDIENTE' }}</div>
<div class="line"></div>
<div class="bold">Destinatario</div>
<div>{{ $destinatario['nombre'] }}</div>
<div>{{ $destinatario['tipo_documento'] }} {{ $destinatario['numero_documento'] }}</div>
<div class="line"></div>
<div class="bold">Partida</div>
<div>{{ $guia->punto_partida_ubigeo }} {{ $guia->punto_partida_direccion }}</div>
<div class="bold">Llegada</div>
<div>{{ $guia->punto_llegada_ubigeo }} {{ $guia->punto_llegada_direccion }}</div>
<div class="line"></div>
<table>
@foreach($detalles as $detalle)
    <tr>
        <td>{{ $detalle->descripcion }}<br>{{ $detalle->unidad_medida }} x {{ number_format((float) $detalle->cantidad, 4) }}</td>
    </tr>
@endforeach
</table>
<div class="line"></div>
<div>Peso: {{ $guia->peso_total }} {{ $guia->unidad_peso }} | Bultos: {{ $guia->numero_bultos ?: '-' }}</div>
@if($guia->modalidad_transporte === '01')
    <div>Transportista: {{ $guia->transportista_razon_social }}</div>
@else
    <div>Conductor: {{ $guia->conductor_nombre }}</div>
    <div>Lic: {{ $guia->conductor_licencia }} | Placa: {{ $guia->vehiculo_placa }}</div>
@endif
<div class="line"></div>
<div class="center"><img class="qr" src="{{ $qr }}" alt="QR"></div>
<div>Hash: {{ $guia->hash ?: '-' }}</div>
@if($anulada)<div class="center bold">DOCUMENTO ANULADO</div>@endif
</body>
</html>