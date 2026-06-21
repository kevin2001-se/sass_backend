<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #667085; margin-bottom: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d9dee8; padding: 5px; vertical-align: top; }
        th { background: #eef2ff; font-weight: bold; }
        .summary { margin-bottom: 12px; width: 50%; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">Generado: {{ $generated_at }}</div>

    @if (! empty($summary))
        <table class="summary">
            <tbody>
            @foreach ($summary as $key => $value)
                <tr>
                    <th>{{ $key }}</th>
                    <td>{{ $value }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
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
