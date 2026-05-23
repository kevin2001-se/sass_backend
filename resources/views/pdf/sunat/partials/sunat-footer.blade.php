<div class="footer">
    <table class="no-border">
        <tr>
            <td style="width: 130px;"><img class="qr" src="{{ $qr }}" alt="QR"></td>
            <td>
                <div><strong>Hash:</strong> {{ $comprobante->hash }}</div>
                <div><strong>Estado SUNAT:</strong> {{ $comprobante->estado_sunat }}</div>
                <div><strong>Mensaje:</strong> {{ $comprobante->mensaje_respuesta }}</div>
                <div><strong>QR:</strong> {{ $comprobante->qr_text }}</div>
            </td>
        </tr>
    </table>
</div>
