<div class="center">
    <strong>{{ $empresa['razon_social'] }}</strong><br>
    RUC {{ $empresa['ruc'] }}<br>
    {{ $empresa['direccion'] }}<br>
    <div class="line"></div>
    <strong>{{ $comprobante->tipo_comprobante }}</strong><br>
    <strong>{{ $comprobante->numero_comprobante }}</strong><br>
    {{ $comprobante->fecha_emision->format('Y-m-d H:i') }}
</div>
<div class="line"></div>
Cliente: {{ $cliente['nombre'] }}<br>
Doc: {{ $cliente['tipo_documento'] }} {{ $cliente['numero_documento'] }}
@if($guia)
<div class="line"></div>
Traslado: {{ $guia->fecha_traslado?->format('Y-m-d') }}<br>
Partida: {{ $guia->punto_partida_direccion }}<br>
Llegada: {{ $guia->punto_llegada_direccion }}<br>
@endif
<div class="line"></div>
<table>
@foreach($detalles as $detalle)
    <tr>
        <td colspan="2">{{ $detalle['descripcion'] }}</td>
    </tr>
    <tr>
        <td>{{ number_format((float) $detalle['cantidad'], 2) }} {{ $detalle['unidad_medida'] }}</td>
        <td class="right">{{ isset($detalle['total']) ? number_format((float) $detalle['total'], 2) : '' }}</td>
    </tr>
@endforeach
</table>
<div class="line"></div>
@if($totales['total'] > 0)
<table>
    <tr><td>Subtotal</td><td class="right">{{ number_format((float) $totales['subtotal'], 2) }}</td></tr>
    <tr><td>IGV</td><td class="right">{{ number_format((float) $totales['igv'], 2) }}</td></tr>
    <tr><td><strong>Total</strong></td><td class="right"><strong>{{ number_format((float) $totales['total'], 2) }}</strong></td></tr>
</table>
{{ $total_letras }}
<div class="line"></div>
@endif
<div class="center">
    <img class="qr" src="{{ $qr }}" alt="QR"><br>
    Hash: {{ $comprobante->hash }}<br>
    SUNAT: {{ $comprobante->estado_sunat }}
</div>
