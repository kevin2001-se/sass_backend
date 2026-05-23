<table>
    <thead>
        <tr>
            <th>Cant.</th>
            <th>Descripcion</th>
            <th>Und.</th>
            <th>P. Unit.</th>
            <th>Desc.</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($detalles as $detalle)
            <tr>
                <td class="right">{{ number_format((float) $detalle['cantidad'], 2) }}</td>
                <td>{{ $detalle['descripcion'] }}</td>
                <td>{{ $detalle['unidad_medida'] }}</td>
                <td class="right">{{ isset($detalle['precio_unitario']) ? number_format((float) $detalle['precio_unitario'], 2) : '-' }}</td>
                <td class="right">{{ isset($detalle['descuento']) ? number_format((float) $detalle['descuento'], 2) : '-' }}</td>
                <td class="right">{{ isset($detalle['total']) ? number_format((float) $detalle['total'], 2) : '-' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
