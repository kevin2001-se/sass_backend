<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d9dee8; padding: 6px; mso-number-format:"\@"; }
        th { background: #eef2ff; font-weight: bold; }
        .title { font-size: 18px; font-weight: bold; margin-bottom: 8px; }
        .meta { color: #667085; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="title">{{ $title }}</div>
    <div class="meta">Generado: {{ $generated_at }}</div>

    @if (! empty($summary))
        <table>
            <tbody>
            @foreach ($summary as $key => $value)
                <tr>
                    <th>{{ $key }}</th>
                    <td>{{ $value }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <br>
    @endif

    <table>
        <thead>
        <tr>
            @foreach ($headers as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($row as $value)
                    <td>{{ $value }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($headers) }}">Sin datos</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
