<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; margin: 24px; }
        h1, h2, h3, p { margin: 0; }
        .header { display: table; width: 100%; margin-bottom: 18px; }
        .header > div { display: table-cell; vertical-align: top; }
        .company { width: 60%; }
        .doc { width: 40%; border: 1px solid #111827; padding: 12px; text-align: center; }
        .doc h1 { font-size: 17px; margin-bottom: 8px; }
        .muted { color: #6b7280; }
        .section { border: 1px solid #d1d5db; padding: 10px; margin-bottom: 12px; }
        .grid { display: table; width: 100%; }
        .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #eef2ff; color: #312e81; font-weight: 700; }
        th, td { border: 1px solid #d1d5db; padding: 6px; vertical-align: top; }
        .right { text-align: right; }
        .center { text-align: center; }
        .totals { width: 280px; margin-left: auto; margin-top: 12px; }
        .stamp { position: fixed; top: 320px; left: 120px; right: 120px; text-align: center; font-size: 64px; color: rgba(220, 38, 38, .18); border: 5px solid rgba(220, 38, 38, .15); transform: rotate(-18deg); padding: 20px; }
    </style>
</head>
<body>
@if($anulada)
    <div class="stamp">ANULADA</div>
@endif

<div class="header">
    <div class="company">
        <h2>{{ $empresa->razon_social ?? $empresa->nombre ?? 'EMPRESA' }}</h2>
        <p><strong>RUC:</strong> {{ $empresa->ruc ?? '-' }}</p>
        <p><strong>Direccion:</strong> {{ $empresa->direccion_fiscal ?? $empresa->direccion ?? '-' }}</p>
        <p><strong>Tienda:</strong> {{ $tienda->nombre ?? '-' }}</p>
    </div>
    <div class="doc">
        <h1>COMPRA</h1>
        <p>{{ $compra->tipo_comprobante }} {{ $compra->serie }}-{{ $compra->numero }}</p>
        <p><strong>Estado:</strong> {{ $compra->estado }}</p>
    </div>
</div>

<div class="section grid">
    <div class="col">
        <h3>Proveedor</h3>
        <p>{{ $proveedor->razon_social ?? '-' }}</p>
        <p>{{ $proveedor->tipo_documento ?? '-' }}: {{ $proveedor->numero_documento ?? '-' }}</p>
        <p>{{ $proveedor->direccion ?? '' }}</p>
    </div>
    <div class="col">
        <h3>Datos</h3>
        <p><strong>Fecha emision:</strong> {{ optional($compra->fecha_emision)->format('Y-m-d') }}</p>
        <p><strong>Fecha vencimiento:</strong> {{ optional($compra->fecha_vencimiento)->format('Y-m-d') ?: '-' }}</p>
        <p><strong>Tipo pago:</strong> {{ $compra->tipo_compra }}</p>
        <p><strong>Usuario:</strong> {{ $compra->user->name ?? '-' }}</p>
    </div>
</div>

@if($compra->observacion)
<div class="section">
    <strong>Observacion:</strong> {{ $compra->observacion }}
</div>
@endif

<table>
    <thead>
        <tr>
            <th>Producto</th>
            <th>Presentacion</th>
            <th>Lote</th>
            <th>Venc.</th>
            <th class="right">Cant.</th>
            <th class="right">Costo</th>
            <th class="right">Desc.</th>
            <th class="right">Subtotal</th>
            <th class="right">IGV</th>
            <th class="right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($detalles as $detalle)
            <tr>
                <td>{{ $detalle->producto->nombre ?? $detalle->descripcion }}</td>
                <td>{{ $detalle->presentacion->nombre ?? '-' }}</td>
                <td>{{ $detalle->lote->codigo_lote ?? '-' }}</td>
                <td>{{ optional($detalle->fecha_vencimiento)->format('Y-m-d') ?: optional($detalle->lote?->fecha_vencimiento)->format('Y-m-d') ?: '-' }}</td>
                <td class="right">{{ number_format((float) $detalle->cantidad_presentacion, 2) }}</td>
                <td class="right">{{ number_format((float) $detalle->precio_unitario, 2) }}</td>
                <td class="right">{{ number_format((float) $detalle->descuento, 2) }}</td>
                <td class="right">{{ number_format((float) $detalle->subtotal, 2) }}</td>
                <td class="right">{{ number_format((float) $detalle->igv, 2) }}</td>
                <td class="right">{{ number_format((float) $detalle->total, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Subtotal</td><td class="right">S/ {{ number_format((float) $compra->subtotal, 2) }}</td></tr>
    <tr><td>Descuento</td><td class="right">S/ {{ number_format((float) $compra->total_descuento, 2) }}</td></tr>
    <tr><td>IGV</td><td class="right">S/ {{ number_format((float) $compra->total_igv, 2) }}</td></tr>
    <tr><th>Total</th><th class="right">S/ {{ number_format((float) $compra->total, 2) }}</th></tr>
</table>

<p class="muted" style="margin-top: 24px;">Documento interno generado desde el modulo de compras.</p>
</body>
</html>
