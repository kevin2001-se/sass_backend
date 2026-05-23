<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .header { display: table; width: 100%; margin-bottom: 18px; }
        .header > div { display: table-cell; vertical-align: top; }
        .box { border: 1px solid #111827; padding: 10px; text-align: center; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #f3f4f6; }
        th, td { border: 1px solid #d1d5db; padding: 7px; }
        .right { text-align: right; }
        .totals { width: 280px; margin-left: auto; }
        .title { font-size: 18px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="title">{{ $empresa->nombre ?? 'Empresa' }}</div>
            @if(!empty($empresa->ruc)) <div>RUC: {{ $empresa->ruc }}</div> @endif
            <div>{{ $tienda->nombre ?? '' }}</div>
            <div class="muted">{{ $tienda->direccion ?? $empresa->direccion ?? '' }}</div>
        </div>
        <div style="width: 230px;">
            <div class="box">
                <strong>{{ str_replace("_", " ", $venta->tipo_comprobante) }}</strong><br>
                <strong>{{ $venta->numero_comprobante }}</strong><br>
                {{ $venta->fecha_emision?->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    <div>
        <strong>Cliente:</strong> {{ $cliente?->razon_social ?: $cliente?->nombres ?: 'CLIENTES VARIOS' }}<br>
        <strong>Usuario:</strong> {{ $venta->user?->name ?? '' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="right">Cantidad</th>
                <th class="right">P. Unit.</th>
                <th class="right">Descuento</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalles as $detalle)
                <tr>
                    <td>{{ $detalle->descripcion }}</td>
                    <td class="right">{{ number_format((float) $detalle->cantidad_presentacion, 2) }}</td>
                    <td class="right">S/ {{ number_format((float) $detalle->precio_unitario, 2) }}</td>
                    <td class="right">S/ {{ number_format((float) $detalle->descuento, 2) }}</td>
                    <td class="right">S/ {{ number_format((float) $detalle->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">S/ {{ number_format((float) $venta->subtotal, 2) }}</td></tr>
        <tr><td>Descuento</td><td class="right">S/ {{ number_format((float) $venta->total_descuento, 2) }}</td></tr>
        <tr><td>IGV</td><td class="right">S/ {{ number_format((float) $venta->total_igv, 2) }}</td></tr>
        <tr><td><strong>Total</strong></td><td class="right"><strong>S/ {{ number_format((float) $venta->total, 2) }}</strong></td></tr>
    </table>

    @if(!empty($venta->observacion))
        <p><strong>Observacion:</strong> {{ $venta->observacion }}</p>
    @endif
</body>
</html>