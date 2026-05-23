<table style="width: 45%; margin-left: 55%; margin-top: 12px;">
    <tr><td>Subtotal</td><td class="right">{{ number_format((float) $totales['subtotal'], 2) }}</td></tr>
    <tr><td>Descuento</td><td class="right">{{ number_format((float) $totales['descuento'], 2) }}</td></tr>
    <tr><td>IGV</td><td class="right">{{ number_format((float) $totales['igv'], 2) }}</td></tr>
    <tr><td><strong>Total</strong></td><td class="right"><strong>{{ number_format((float) $totales['total'], 2) }}</strong></td></tr>
</table>
<p><strong>{{ $total_letras }}</strong></p>
