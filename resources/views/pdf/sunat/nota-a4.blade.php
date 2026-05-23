<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        .header { display: table; width: 100%; margin-bottom: 18px; }
        .company { display: table-cell; width: 62%; vertical-align: top; }
        .box { display: table-cell; width: 38%; border: 1px solid #111; text-align: center; padding: 12px; vertical-align: middle; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 6px; }
        th { background: #eee; }
        .right { text-align: right; }
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
        <h2>{{ $comprobante->tipo_comprobante === 'NOTA_CREDITO' ? 'NOTA DE CREDITO ELECTRONICA' : 'NOTA DE DEBITO ELECTRONICA' }}</h2>
        <h3>{{ $comprobante->numero_comprobante }}</h3>
    </div>
</div>
<p><strong>Fecha:</strong> {{ $comprobante->fecha_emision->format('Y-m-d') }}</p>
<p><strong>Cliente:</strong> {{ $cliente['nombre'] }} - {{ $cliente['tipo_documento'] }} {{ $cliente['numero_documento'] }}</p>
<p><strong>Documento relacionado:</strong> {{ $comprobante->notaElectronica?->comprobanteReferencia?->numero_comprobante }}</p>
<p><strong>Motivo:</strong> {{ $comprobante->notaElectronica?->motivo_codigo }} - {{ $comprobante->notaElectronica?->motivo_descripcion }}</p>
@include('pdf.sunat.partials.detalles-tabla')
@include('pdf.sunat.partials.totales')
@include('pdf.sunat.partials.sunat-footer')
</body>
</html>
