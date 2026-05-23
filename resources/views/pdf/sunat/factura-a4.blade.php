<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        .header { display: table; width: 100%; margin-bottom: 18px; }
        .company { display: table-cell; width: 62%; vertical-align: top; }
        .box { display: table-cell; width: 38%; border: 1px solid #111; text-align: center; padding: 12px; vertical-align: middle; }
        h1, h2, h3 { margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 6px; }
        th { background: #eee; }
        .no-border td { border: 0; padding: 3px; }
        .right { text-align: right; }
        .qr { width: 105px; }
        .footer { margin-top: 18px; font-size: 10px; }
    </style>
</head>
<body>
@include('pdf.sunat.partials.venta-a4', ['titulo' => 'FACTURA ELECTRONICA'])
</body>
</html>
