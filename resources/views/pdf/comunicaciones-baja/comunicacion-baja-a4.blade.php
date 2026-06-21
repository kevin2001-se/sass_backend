<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comunicacion de baja {{ $comunicacion->identificador }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; margin: 0; }
        .page { padding: 26px; position: relative; }
        .muted { color: #6b7280; }
        .header { display: table; width: 100%; margin-bottom: 18px; }
        .empresa, .documento { display: table-cell; vertical-align: top; }
        .empresa { width: 58%; }
        .documento { width: 42%; text-align: right; }
        h1 { font-size: 17px; margin: 0 0 8px; letter-spacing: 0; }
        h2 { font-size: 13px; margin: 18px 0 8px; }
        .box { border: 1px solid #d1d5db; border-radius: 4px; padding: 10px; margin-bottom: 12px; }
        .grid { display: table; width: 100%; }
        .col { display: table-cell; width: 33.333%; padding-right: 12px; vertical-align: top; }
        .label { color: #6b7280; font-size: 9px; text-transform: uppercase; }
        .value { font-weight: 700; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #fee2e2; color: #991b1b; font-size: 9px; text-align: left; padding: 6px; border: 1px solid #fecaca; }
        td { padding: 6px; border: 1px solid #e5e7eb; vertical-align: top; }
        .right { text-align: right; }
        .badge { display: inline-block; padding: 3px 7px; border-radius: 999px; background: #fee2e2; color: #991b1b; font-weight: 700; }
        .total { font-size: 14px; font-weight: 800; }
        .watermark { position: absolute; top: 330px; left: 100px; transform: rotate(-25deg); font-size: 72px; color: rgba(220, 38, 38, 0.16); font-weight: 800; }
    </style>
</head>
<body>
<div class="page">
    @if($anulado)
        <div class="watermark">ANULADA</div>
    @endif

    <div class="header">
        <div class="empresa">
            <h1>{{ $configuracion?->razon_social ?: $empresa?->nombre }}</h1>
            <div>RUC: {{ $configuracion?->ruc ?: $empresa?->ruc }}</div>
            <div class="muted">{{ $configuracion?->direccion_fiscal ?: $empresa?->direccion }}</div>
            @if($tienda)
                <div class="muted">Tienda: {{ $tienda->nombre }}</div>
            @endif
        </div>
        <div class="documento">
            <div class="badge">{{ $comunicacion->estado_sunat }}</div>
            <h1>COMUNICACIÓN DE BAJA</h1>
            <div class="total">{{ $comunicacion->identificador }}</div>
        </div>
    </div>

    <div class="box grid">
        <div class="col"><div class="label">Fecha baja</div><div class="value">{{ $comunicacion->fecha_baja?->format('d/m/Y') }}</div></div>
        <div class="col"><div class="label">Fecha generación PDF</div><div class="value">{{ now()->format('d/m/Y H:i') }}</div></div>
        <div class="col"><div class="label">Ticket SUNAT</div><div class="value">{{ $comunicacion->ticket_sunat ?: $comunicacion->ticket ?: '-' }}</div></div>
    </div>

    <div class="box grid">
        <div class="col"><div class="label">Código respuesta</div><div class="value">{{ $comunicacion->codigo_respuesta ?: '-' }}</div></div>
        <div class="col" style="width: 66.666%;"><div class="label">Mensaje respuesta</div><div class="value">{{ $comunicacion->mensaje_respuesta ?: '-' }}</div></div>
    </div>

    <div class="box grid">
        <div class="col"><div class="label">Total documentos</div><div class="value total">{{ $comunicacion->total_documentos }}</div></div>
        <div class="col"><div class="label">Estado interno</div><div class="value">{{ $comunicacion->estado }}</div></div>
        <div class="col"><div class="label">Hash</div><div class="value">{{ $comunicacion->hash ?: '-' }}</div></div>
    </div>

    @if($comunicacion->observacion)
        <div class="box">
            <div class="label">Observación</div>
            <div class="value">{{ $comunicacion->observacion }}</div>
        </div>
    @endif

    <h2>Documentos incluidos</h2>
    <table>
        <thead>
        <tr>
            <th>Tipo</th>
            <th>Serie</th>
            <th>Correlativo</th>
            <th>Número completo</th>
            <th>Motivo baja</th>
            <th>Estado baja</th>
        </tr>
        </thead>
        <tbody>
        @forelse($detalles as $detalle)
            @php($comprobante = $detalle->comprobante ?: $detalle->comprobanteElectronico)
            <tr>
                <td>{{ $detalle->tipo_documento }}</td>
                <td>{{ $detalle->serie }}</td>
                <td>{{ str_pad((string) $detalle->correlativo, 8, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $detalle->numero_completo ?: $detalle->numero_comprobante }}</td>
                <td>{{ $detalle->motivo_baja ?: '-' }}</td>
                <td>{{ $comprobante?->estado_baja ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Sin documentos incluidos.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
