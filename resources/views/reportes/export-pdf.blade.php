<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        .header { border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 14px; }
        h1 { font-size: 19px; margin: 0; color: #111827; }
        .meta { color: #667085; margin-top: 4px; }
        .summary { margin: 0 0 14px; width: 100%; border-collapse: separate; border-spacing: 6px; }
        .summary td { border: 1px solid #d9dee8; border-radius: 6px; padding: 8px; background: #f8fafc; width: 25%; }
        .summary .label { display: block; color: #667085; font-size: 9px; margin-bottom: 3px; }
        .summary .value { display: block; font-size: 12px; font-weight: bold; color: #111827; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data th, table.data td { border: 1px solid #d9dee8; padding: 5px; vertical-align: top; }
        table.data th { background: #eef2ff; color: #312e81; font-weight: bold; }
        table.data tbody tr:nth-child(even) td { background: #f9fafb; }
        .empty { text-align: center; color: #667085; padding: 16px; }
        .footer { position: fixed; bottom: 0; left: 24px; right: 24px; color: #98a2b3; font-size: 8px; border-top: 1px solid #eaecf0; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <div class="meta">Generado: {{ $generated_at }}</div>
    </div>

    @if (! empty($summary))
        <table class="summary">
            <tbody>
            @foreach (array_chunk($summary, 4, true) as $chunk)
                <tr>
                    @foreach ($chunk as $key => $value)
                        <td>
                            <span class="label">{{ str_replace('_', ' ', strtoupper($key)) }}</span>
                            <span class="value">{{ $value }}</span>
                        </td>
                    @endforeach
                    @for ($i = count($chunk); $i < 4; $i++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <table class="data">
        <thead>
        <tr>
            @foreach ($headers as $header)
                <th>{{ str_replace('_', ' ', strtoupper($header)) }}</th>
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
                <td class="empty" colspan="{{ max(count($headers), 1) }}">Sin datos para mostrar.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="footer">Reporte generado por Botica SaaS</div>
</body>
</html>