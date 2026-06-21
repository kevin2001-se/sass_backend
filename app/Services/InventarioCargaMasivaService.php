<?php

namespace App\Services;

use App\Models\InventarioMovimiento;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Services\Support\SimpleXlsxWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class InventarioCargaMasivaService
{
    public function __construct(
        private readonly InventarioService $inventarioService,
        private readonly SimpleXlsxWriter $xlsxWriter,
    ) {
    }


    public function plantilla(string $tipo, array $scope)
    {
        $presentaciones = ProductoPresentacion::with(['producto', 'unidadMedida'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('estado', true)
            ->whereHas('producto', fn ($query) => $query->where('estado', true))
            ->orderBy('producto_id')
            ->orderByDesc('es_principal')
            ->get();

        $headers = match ($tipo) {
            'lotes' => ['codigo_barra', 'codigo_interno', 'producto', 'presentacion', 'codigo_lote', 'fecha_vencimiento'],
            'ajuste' => ['codigo_barra', 'codigo_interno', 'producto', 'presentacion', 'codigo_lote', 'fecha_vencimiento', 'cantidad', 'tipo_ajuste', 'observacion'],
            default => ['codigo_barra', 'codigo_interno', 'producto', 'presentacion', 'codigo_lote', 'fecha_vencimiento', 'cantidad', 'observacion'],
        };

        $rows = $presentaciones->map(function (ProductoPresentacion $presentacion) use ($headers, $tipo) {
            $producto = $presentacion->producto;
            $base = [
                'producto_id' => $producto->id,
                'codigo_interno' => $producto->codigo_interno,
                'producto' => $producto->nombre,
                'producto_presentacion_id' => $presentacion->id,
                'presentacion' => $presentacion->nombre,
                'codigo_barra' => $presentacion->codigo_barra,
                'factor_conversion' => $presentacion->factor_conversion,
                'maneja_lote' => $producto->maneja_lote ? 'SI' : 'NO',
                'maneja_vencimiento' => $producto->maneja_vencimiento ? 'SI' : 'NO',
                'codigo_lote' => '',
                'fecha_vencimiento' => '',
                'cantidad' => '',
                'motivo' => '',
                'tipo_ajuste' => $tipo === 'ajuste' ? 'POSITIVO' : '',
                'observacion' => '',
            ];

            return collect($headers)->mapWithKeys(fn ($header) => [$header => $base[$header] ?? ''])->all();
        })->all();

        $path = $this->xlsxWriter->make('Plantilla '.strtoupper($tipo), $headers, $rows, [
            'nota' => $tipo === 'lotes'
                ? 'Complete codigo_lote y fecha_vencimiento si corresponde.'
                : 'Complete cantidad. Las filas sin cantidad no se procesan.',
        ]);

        return response()->download($path, 'plantilla-'.$tipo.'-inventario.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
    public function importarMovimientos(UploadedFile $file, string $operacion, array $scope, array $options = []): array
    {
        $rows = $this->leerArchivo($file);
        $resultado = $this->emptyResult();
        $motivoDefault = $options['motivo'] ?? 'Carga masiva de inventario';

        foreach ($rows as $index => $row) {
            $numeroFila = $index + 2;
            $cantidad = $this->cell($row, ['cantidad', 'cantidad_presentacion', 'qty']);

            if ($cantidad === null || $cantidad === '') {
                $resultado['omitidas'][] = ['fila' => $numeroFila, 'motivo' => 'Cantidad vacia.'];
                continue;
            }

            try {
                $presentacion = $this->resolverPresentacion($row, $scope);
                $producto = $presentacion->producto;
                $lote = $this->resolverLoteParaMovimiento($row, $scope, $producto, $operacion, $options);
                $tipoAjuste = strtoupper((string) ($this->cell($row, ['tipo_ajuste']) ?: ($options['tipo_ajuste'] ?? '')));

                $payload = array_merge($scope, [
                    'producto_id' => $producto->id,
                    'producto_presentacion_id' => $presentacion->id,
                    'lote_id' => $lote?->id,
                    'cantidad_presentacion' => (float) $cantidad,
                    'motivo' => $this->cell($row, ['motivo']) ?: $motivoDefault,
                    'observacion' => $this->cell($row, ['observacion']) ?: 'Carga masiva fila '.$numeroFila,
                    'referencia_tipo' => 'CARGA_MASIVA',
                ]);

                $movimiento = match ($operacion) {
                    'entrada' => $this->inventarioService->aumentarStock($payload),
                    'salida' => $this->inventarioService->disminuirStock($payload),
                    'ajuste' => $this->inventarioService->ajustarStock(array_merge($payload, ['tipo_ajuste' => $tipoAjuste])),
                    default => throw ValidationException::withMessages(['tipo' => ['Operacion masiva no soportada.']]),
                };

                $resultado['procesadas'][] = [
                    'fila' => $numeroFila,
                    'producto' => $producto->nombre,
                    'presentacion' => $presentacion->nombre,
                    'lote' => $lote?->codigo_lote,
                    'cantidad' => (float) $cantidad,
                    'movimiento_id' => $movimiento->id,
                ];
            } catch (ValidationException $exception) {
                $resultado['errores'][] = ['fila' => $numeroFila, 'errores' => $exception->errors()];
            } catch (\Throwable $exception) {
                $resultado['errores'][] = ['fila' => $numeroFila, 'errores' => ['fila' => [$exception->getMessage()]]];
            }
        }

        return $this->withCounts($resultado);
    }

    public function importarLotes(UploadedFile $file, array $scope): array
    {
        $rows = $this->leerArchivo($file);
        $resultado = $this->emptyResult();

        foreach ($rows as $index => $row) {
            $numeroFila = $index + 2;

            try {
                $presentacion = $this->resolverPresentacion($row, $scope);
                $producto = $presentacion->producto;
                $codigoLote = trim((string) $this->cell($row, ['codigo_lote', 'lote']));

                if ($codigoLote === '') {
                    throw ValidationException::withMessages(['codigo_lote' => ['El codigo de lote es obligatorio.']]);
                }

                if (! $producto->maneja_lote) {
                    throw ValidationException::withMessages(['producto' => ['El producto no maneja lotes.']]);
                }

                $fechaVencimiento = $this->normalizeDate($this->cell($row, ['fecha_vencimiento', 'vencimiento']));

                if ($producto->maneja_vencimiento && ! $fechaVencimiento) {
                    throw ValidationException::withMessages(['fecha_vencimiento' => ['La fecha de vencimiento es obligatoria para este producto.']]);
                }

                $exists = Lote::where('empresa_id', $scope['empresa_id'])
                    ->where('producto_id', $producto->id)
                    ->where('codigo_lote', $codigoLote)
                    ->first();

                if ($exists) {
                    $resultado['omitidas'][] = ['fila' => $numeroFila, 'motivo' => 'El lote ya existe para este producto.'];
                    continue;
                }

                $lote = Lote::create([
                    'tenant_id' => $scope['tenant_id'],
                    'empresa_id' => $scope['empresa_id'],
                    'producto_id' => $producto->id,
                    'codigo_lote' => $codigoLote,
                    'fecha_vencimiento' => $fechaVencimiento ?: null,
                    'estado' => true,
                ]);

                $resultado['procesadas'][] = [
                    'fila' => $numeroFila,
                    'producto' => $producto->nombre,
                    'lote' => $lote->codigo_lote,
                    'fecha_vencimiento' => optional($lote->fecha_vencimiento)->toDateString(),
                ];
            } catch (ValidationException $exception) {
                $resultado['errores'][] = ['fila' => $numeroFila, 'errores' => $exception->errors()];
            } catch (\Throwable $exception) {
                $resultado['errores'][] = ['fila' => $numeroFila, 'errores' => ['fila' => [$exception->getMessage()]]];
            }
        }

        return $this->withCounts($resultado);
    }

    protected function resolverPresentacion(array $row, array $scope): ProductoPresentacion
    {
        $codigoBarra = trim((string) $this->cell($row, ['codigo_barra', 'codigo_barras', 'barcode']));
        $presentacionId = $this->cell($row, ['producto_presentacion_id', 'presentacion_id']);

        $query = ProductoPresentacion::with('producto')
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('estado', true);

        if ($presentacionId) {
            $presentacion = (clone $query)->find((int) $presentacionId);
        } elseif ($codigoBarra !== '') {
            $presentacion = (clone $query)->where('codigo_barra', $codigoBarra)->first();
        } else {
            $producto = $this->resolverProducto($row, $scope);
            $nombrePresentacion = trim((string) $this->cell($row, ['presentacion', 'unidad', 'nombre_presentacion']));
            $presentacion = (clone $query)
                ->where('producto_id', $producto->id)
                ->when($nombrePresentacion !== '', fn ($q) => $q->where('nombre', 'ilike', $nombrePresentacion))
                ->orderByDesc('es_principal')
                ->first();
        }

        if (! $presentacion?->producto) {
            throw ValidationException::withMessages(['codigo_barra' => ['No se encontro la presentacion del producto.']]);
        }

        return $presentacion;
    }

    protected function resolverProducto(array $row, array $scope): Producto
    {
        $productoId = $this->cell($row, ['producto_id']);
        $codigoInterno = trim((string) $this->cell($row, ['codigo_interno', 'codigo_producto']));
        $nombre = trim((string) $this->cell($row, ['producto', 'nombre_producto', 'nombre']));

        $query = Producto::where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('estado', true);

        $producto = $productoId
            ? (clone $query)->find((int) $productoId)
            : ($codigoInterno !== ''
                ? (clone $query)->where('codigo_interno', $codigoInterno)->first()
                : (clone $query)->where('nombre', 'ilike', $nombre)->first());

        if (! $producto) {
            throw ValidationException::withMessages(['producto' => ['No se encontro el producto.']]);
        }

        return $producto;
    }

    protected function resolverLoteParaMovimiento(array $row, array $scope, Producto $producto, string $operacion, array $options): ?Lote
    {
        if (! $producto->maneja_lote) {
            return null;
        }

        $loteId = $this->cell($row, ['lote_id']);
        $codigoLote = trim((string) $this->cell($row, ['codigo_lote', 'lote']));
        $fechaVencimiento = $this->normalizeDate($this->cell($row, ['fecha_vencimiento', 'vencimiento']));

        $query = Lote::where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('producto_id', $producto->id);

        $lote = $loteId ? (clone $query)->find((int) $loteId) : null;
        $lote = $lote ?: ($codigoLote !== '' ? (clone $query)->where('codigo_lote', $codigoLote)->first() : null);

        $tipoAjuste = strtoupper((string) ($this->cell($row, ['tipo_ajuste']) ?: ($options['tipo_ajuste'] ?? '')));
        $puedeCrear = $operacion === 'entrada' || ($operacion === 'ajuste' && $tipoAjuste === 'POSITIVO');

        if (! $lote && $puedeCrear && $codigoLote !== '') {
            if ($producto->maneja_vencimiento && ! $fechaVencimiento) {
                throw ValidationException::withMessages(['fecha_vencimiento' => ['La fecha de vencimiento es obligatoria para crear el lote.']]);
            }

            $lote = Lote::create([
                'tenant_id' => $scope['tenant_id'],
                'empresa_id' => $scope['empresa_id'],
                'producto_id' => $producto->id,
                'codigo_lote' => $codigoLote,
                'fecha_vencimiento' => $fechaVencimiento ?: null,
                'estado' => true,
            ]);
        }

        if (! $lote) {
            throw ValidationException::withMessages(['codigo_lote' => ['El lote es obligatorio o no existe para este producto.']]);
        }

        return $lote;
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return now()->createFromTimestamp(0)->addDays(((int) $value) - 25569)->toDateString();
        }

        $value = trim((string) $value);
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            [$day, $month, $year] = explode('/', $value);
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        return $value;
    }
    protected function leerArchivo(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'xlsx') {
            return $this->leerXlsx($file->getRealPath());
        }

        return $this->leerTextoTabular($file->getRealPath());
    }

    protected function leerTextoTabular(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        if (! $lines) return [];

        $delimiter = str_contains($lines[0], ';') ? ';' : (str_contains($lines[0], "\t") ? "\t" : ',');
        $headers = array_map(fn ($header) => $this->normalizeHeader($header), str_getcsv(array_shift($lines), $delimiter));

        return collect($lines)
            ->map(fn ($line) => array_combine($headers, str_getcsv($line, $delimiter)) ?: [])
            ->filter()
            ->values()
            ->all();
    }

    protected function leerXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages(['archivo' => ['El servidor no tiene ZipArchive habilitado para leer XLSX.']]);
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['archivo' => ['No se pudo leer el archivo XLSX.']]);
        }

        $shared = $this->xlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! $sheetXml) {
            throw ValidationException::withMessages(['archivo' => ['El XLSX no contiene la hoja principal.']]);
        }

        $sheet = simplexml_load_string($sheetXml);
        $matrix = [];

        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $column = $this->xlsxColumnIndex($ref);
                $value = (string) ($cell->v ?? '');
                if ((string) $cell['t'] === 's') {
                    $value = $shared[(int) $value] ?? '';
                }
                $cells[$column] = $value;
            }
            if ($cells) {
                $matrix[] = $cells;
            }
        }

        if (! $matrix) return [];

        $headerRow = array_shift($matrix);
        ksort($headerRow);
        $headers = array_map(fn ($header) => $this->normalizeHeader($header), array_values($headerRow));

        return collect($matrix)->map(function ($row) use ($headers) {
            $values = [];
            for ($i = 0; $i < count($headers); $i++) {
                $values[$headers[$i]] = $row[$i] ?? null;
            }
            return $values;
        })->values()->all();
    }

    protected function xlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (! $xml) return [];

        $strings = simplexml_load_string($xml);
        $values = [];

        foreach ($strings->si as $item) {
            $values[] = isset($item->t)
                ? (string) $item->t
                : collect($item->r)->map(fn ($run) => (string) $run->t)->implode('');
        }

        return $values;
    }

    protected function xlsxColumnIndex(string $cellRef): int
    {
        preg_match('/^[A-Z]+/', $cellRef, $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }
        return $index - 1;
    }

    protected function cell(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeHeader($key);
            if (array_key_exists($normalized, $row)) {
                return is_string($row[$normalized]) ? trim($row[$normalized]) : $row[$normalized];
            }
        }
        return null;
    }

    protected function normalizeHeader(string $header): string
    {
        return Str::of($header)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    protected function emptyResult(): array
    {
        return ['procesadas' => [], 'omitidas' => [], 'errores' => []];
    }

    protected function withCounts(array $resultado): array
    {
        return array_merge($resultado, [
            'total_procesadas' => count($resultado['procesadas']),
            'total_omitidas' => count($resultado['omitidas']),
            'total_errores' => count($resultado['errores']),
        ]);
    }
}
