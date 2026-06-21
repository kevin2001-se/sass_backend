<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d9dee8; padding: 6px; mso-number-format:"\@"; }
        th { background: #eef2ff; font-weight: bold; }
        .note { margin-bottom: 12px; color: #475467; }
    </style>
</head>
<body>
    <h3>Plantilla carga masiva - {{ strtoupper($tipo) }}</h3>
    <div class="note">
        Complete las columnas editables. Para movimientos, si la columna cantidad queda vacia, la fila se omitira.
    </div>
    <table>
        <thead>
        <tr>
            @foreach ($headers as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                @foreach ($headers as $header)
                    <td>{{ $row[$header] ?? '' }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
