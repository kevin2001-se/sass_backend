<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        .center { text-align: center; }
        .line { border-top: 1px dashed #9ca3af; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2px 0; vertical-align: top; }
        .right { text-align: right; }
        .muted { color: #6b7280; }
        .total { font-size: 13px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="center">
        <strong>{{ $empresa->nombre ?? 'Empresa' }}</strong><br>
        @if(!empty($empresa->ruc)) RUC: {{ $empresa->ruc }}<br>@endif
        {{ $tienda->nombre ?? '' }}<br>
        <span class="muted">{{ $tienda->direccion ?? $empresa->direccion ?? '' }}</span>
    </div>
    <div class="line"></div>
    <div class="center">
        <strong>{{ $venta->tipo_comprobante }}</strong><br>
        <strong>{{ $venta->numero_comprobante }}</strong><br>
        {{ $venta->fecha_emision?->format('d/m/Y H:i') }}
    </div>
    <div class="line"></div>
    Cliente: {{ $cliente?->razon_social ?: $cliente?->nombres ?: 'CLIENTES VARIOS' }}<br>
    Usuario: {{ $venta->user?->name ?? '' }}
    <div class="line"></div>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="right">Cant.</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalles as $detalle)
                <tr>
                    <td>{{ $detalle->descripcion }}<br><span class="muted">S/ {{ number_format((float) $detalle->precio_unitario, 2) }}</span></td>
                    <td class="right">{{ number_format((float) $detalle->cantidad_presentacion, 2) }}</td>
                    <td class="right">S/ {{ number_format((float) $detalle->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="line"></div>
    <table>
        <tr><td>Subtotal</td><td class="right">S/ {{ number_format((float) $venta->subtotal, 2) }}</td></tr>
        <tr><td>Descuento</td><td class="right">S/ {{ number_format((float) $venta->total_descuento, 2) }}</td></tr>
        <tr><td>IGV</td><td class="right">S/ {{ number_format((float) $venta->total_igv, 2) }}</td></tr>
        <tr class="total"><td>Total</td><td class="right">S/ {{ number_format((float) $venta->total, 2) }}</td></tr>
    </table>
    <div class="line"></div>
    <div class="center">Gracias por su compra</div>
</body>
</html>
