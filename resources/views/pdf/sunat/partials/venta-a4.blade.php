<div class="header">
    <div class="company">
        <h2>{{ $empresa['razon_social'] }}</h2>
        <div>RUC: {{ $empresa['ruc'] }}</div>
        <div>{{ $empresa['direccion'] }}</div>
        <div>{{ $empresa['nombre_comercial'] }}</div>
    </div>
    <div class="box">
        <h2>{{ $titulo }}</h2>
        <h3>{{ $comprobante->numero_comprobante }}</h3>
    </div>
</div>
<table class="no-border">
    <tr><td><strong>Fecha emision:</strong> {{ $comprobante->fecha_emision->format('Y-m-d') }}</td></tr>
    <tr><td><strong>Cliente:</strong> {{ $cliente['nombre'] }}</td></tr>
    <tr><td><strong>Documento:</strong> {{ $cliente['tipo_documento'] }} {{ $cliente['numero_documento'] }}</td></tr>
    <tr><td><strong>Direccion:</strong> {{ $cliente['direccion'] }}</td></tr>
</table>
@include('pdf.sunat.partials.detalles-tabla')
@include('pdf.sunat.partials.totales')
<p><strong>Forma de pago:</strong>
    @foreach($pagos as $pago)
        {{ $pago->metodo_pago }} {{ number_format((float) $pago->monto, 2) }}@if(!$loop->last), @endif
    @endforeach
</p>
@include('pdf.sunat.partials.sunat-footer')
