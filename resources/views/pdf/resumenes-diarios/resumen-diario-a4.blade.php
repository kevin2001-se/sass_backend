<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen diario {{ $resumen->identificador }}</title>
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
        th { background: #eef2ff; color: #3730a3; font-size: 9px; text-align: left; padding: 6px; border: 1px solid #c7d2fe; }
        td { padding: 6px; border: 1px solid #e5e7eb; vertical-align: top; }
        .right { text-align: right; }
        .badge { display: inline-block; padding: 3px 7px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-weight: 700; }
        .total { font-size: 14px; font-weight: 800; }
        .watermark { position: absolute; top: 330px; left: 100px; transform: rotate(-25deg); font-size: 72px; color: rgba(220, 38, 38, 0.16); font-weight: 800; }
    </style>
</head>
<body>
<div class="page">
    @if($anulado)
        <div class="watermark">ANULADO</div>
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
            <div class="badge">{{ $resumen->estado_sunat }}</div>
            <h1>RESUMEN DIARIO DE BOLETAS</h1>
            <div class="total">{{ $resumen->identificador }}</div>
        </div>
    </div>

    <div class="box grid">
        <div class="col"><div class="label">Fecha resumen</div><div class="value">{{ $resumen->fecha_resumen?->format('d/m/Y') }}</div></div>
        <div class="col"><div class="label">Fecha generación PDF</div><div class="value">{{ now()->format('d/m/Y H:i') }}</div></div>
        <div class="col"><div class="label">Ticket SUNAT</div><div class="value">{{ $resumen->ticket_sunat ?: $resumen->ticket ?: '-' }}</div></div>
    </div>

    <div class="box grid">
        <div class="col"><div class="label">Código respuesta</div><div class="value">{{ $resumen->codigo_respuesta ?: '-' }}</div></div>
        <div class="col" style="width: 66.666%;"><div class="label">Mensaje respuesta</div><div class="value">{{ $resumen->mensaje_respuesta ?: '-' }}</div></div>
    </div>

    <div class="box grid">
        <div class="col"><div class="label">Boletas</div><div class="value">{{ $resumen->total_boletas }}</div></div>
        <div class="col"><div class="label">Notas de crédito</div><div class="value">{{ $resumen->total_notas_credito }}</div></div>
        <div class="col"><div class="label">Notas de débito</div><div class="value">{{ $resumen->total_notas_debito }}</div></div>
    </div>

    <div class="box grid">
        <div class="col"><div class="label">Total documentos</div><div class="value">{{ $resumen->total_documentos }}</div></div>
        <div class="col"><div class="label">Monto total</div><div class="value total">S/ {{ number_format((float) $resumen->monto_total, 2) }}</div></div>
        <div class="col"><div class="label">Estado interno</div><div class="value">{{ $resumen->estado }}</div></div>
    </div>

    <h2>Documentos incluidos</h2>
    <table>
        <thead>
        <tr>
            <th>Tipo</th><th>Serie</th><th>Correlativo</th><th>Cliente</th><th class="right">Subtotal</th><th class="right">IGV</th><th class="right">Total</th><th>Estado doc.</th>
        </tr>
        </thead>
        <tbody>
        @forelse($detalles as $detalle)
            <tr>
                <td>{{ $detalle->tipo_documento }}</td>
                <td>{{ $detalle->serie }}</td>
                <td>{{ str_pad((string) $detalle->correlativo, 8, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $detalle->cliente_nombre ?: '-' }}<br><span class="muted">{{ $detalle->cliente_tipo_documento ?: '-' }} {{ $detalle->cliente_numero_documento ?: '' }}</span></td>
                <td class="right">{{ number_format((float) $detalle->subtotal, 2) }}</td>
                <td class="right">{{ number_format((float) $detalle->total_igv, 2) }}</td>
                <td class="right">{{ number_format((float) $detalle->total, 2) }}</td>
                <td>{{ $detalle->estado_documento ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">Sin documentos incluidos.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>