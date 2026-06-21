<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\InventarioMovimiento;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CompraService
{
    private const IGV = 0.18;

    public function __construct(private readonly InventarioService $inventarioService, private readonly CuentaPorPagarService $cuentaPorPagarService) {}

    public function listar(Request $request)
    {
        return Compra::with(['proveedor', 'user'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('proveedor_id'), fn ($q) => $q->where('proveedor_id', $request->integer('proveedor_id')))
            ->when($request->filled('tipo_documento'), fn ($q) => $q->where('tipo_comprobante', $request->input('tipo_documento')))
            ->when($request->filled('tipo_pago'), fn ($q) => $q->where('tipo_compra', $request->input('tipo_pago')))
            ->when($request->filled('fecha_inicio'), fn ($q) => $q->whereDate('fecha_emision', '>=', $request->input('fecha_inicio')))
            ->when($request->filled('fecha_fin'), fn ($q) => $q->whereDate('fecha_emision', '<=', $request->input('fecha_fin')))
            ->when($request->filled('numero_documento'), function ($q) use ($request) {
                $numero = trim((string) $request->input('numero_documento'));
                $q->where(function ($sub) use ($numero) {
                    $sub->where('serie', 'ilike', '%'.$numero.'%')
                        ->orWhere('numero', 'ilike', '%'.$numero.'%')
                        ->orWhereRaw("CONCAT(serie, '-', numero) ILIKE ?", ['%'.$numero.'%']);
                });
            })
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));
    }

    public function obtener(int $id, array $scope): Compra
    {
        return $this->cargarCompra(Compra::where('tenant_id', $scope['tenant_id'])->where('empresa_id', $scope['empresa_id'])->where('tienda_id', $scope['tienda_id'])->findOrFail($id));
    }

    public function registrar(array $data): Compra
    {
        return $this->registrarCompra($data);
    }

    public function registrarCompra(array $data): Compra
    {
        return DB::transaction(function () use ($data) {
            $proveedor = Proveedor::where('tenant_id', $data['tenant_id'])->where('empresa_id', $data['empresa_id'])->findOrFail($data['proveedor_id']);
            $detalles = $this->calcularDetalles($data);
            $this->validarDetallesDuplicados($detalles);
            $totales = $this->calcularTotales($detalles);

            $compra = Compra::create([
                'tenant_id' => $data['tenant_id'],
                'empresa_id' => $data['empresa_id'],
                'tienda_id' => $data['tienda_id'],
                'proveedor_id' => $proveedor->id,
                'user_id' => $data['user_id'],
                'created_by' => $data['user_id'],
                'tipo_comprobante' => $data['tipo_comprobante'],
                'serie' => strtoupper($data['serie']),
                'numero' => (string) $data['numero'],
                'tipo_compra' => $data['tipo_compra'],
                'moneda' => $data['moneda'] ?? parametro('moneda_default', 'PEN'),
                'fecha_emision' => $data['fecha_emision'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'subtotal' => $totales['subtotal'],
                'total_igv' => $totales['igv'],
                'total_descuento' => $totales['descuento'],
                'total' => $totales['total'],
                'estado' => Compra::REGISTRADA,
                'observacion' => $data['observacion'] ?? null,
            ]);

            foreach ($detalles as $detalle) {
                $compra->detalles()->create($detalle);
                $this->registrarEntradaInventario($compra, $detalle, $data);
            }

            if ($compra->tipo_compra === Compra::CREDITO && (bool) parametro('crear_cxp_automaticamente', true)) {
                $this->cuentaPorPagarService->crearDesdeCompra($compra);
            } elseif ($compra->tipo_compra === Compra::CREDITO) {
                Log::info('Parametro crear_cxp_automaticamente desactivado: compra credito sin CxP automatica.', [
                    'compra_id' => $compra->id,
                    'empresa_id' => $compra->empresa_id,
                    'tienda_id' => $compra->tienda_id,
                ]);
            }

            return $this->cargarCompra($compra->refresh());
        });
    }

    public function anular(Compra $compra, string $motivo, array $scope): Compra
    {
        return $this->anularCompra($compra->id, $motivo, $scope);
    }

    public function anularCompra(int $compraId, string $motivo, array $scope): Compra
    {
        return DB::transaction(function () use ($compraId, $motivo, $scope) {
            $compra = Compra::with('detalles')->where('tenant_id', $scope['tenant_id'])->where('empresa_id', $scope['empresa_id'])->where('tienda_id', $scope['tienda_id'])->lockForUpdate()->findOrFail($compraId);
            if ($compra->estado !== Compra::REGISTRADA) {
                throw ValidationException::withMessages(['compra' => ['Solo se puede anular una compra registrada.']]);
            }

            $this->cuentaPorPagarService->anularPorCompraSiSinPagos($compra);

            foreach ($compra->detalles as $detalle) {
                $this->inventarioService->disminuirStock([
                    'tenant_id' => $compra->tenant_id,
                    'empresa_id' => $compra->empresa_id,
                    'tienda_id' => $compra->tienda_id,
                    'producto_id' => $detalle->producto_id,
                    'producto_presentacion_id' => $detalle->producto_presentacion_id,
                    'lote_id' => $detalle->lote_id,
                    'cantidad_presentacion' => $detalle->cantidad_presentacion,
                    'motivo' => 'Anulacion de compra '.$compra->serie.'-'.$compra->numero,
                    'tipo_movimiento' => InventarioMovimiento::AJUSTE_NEGATIVO,
                    'referencia_tipo' => 'COMPRA',
                    'referencia_id' => $compra->id,
                    'observacion' => $motivo,
                    'user_id' => $scope['user_id'],
                ]);
            }

            $compra->update([
                'estado' => Compra::ANULADA,
                'anulado_by' => $scope['user_id'],
                'anulado_at' => now(),
                'motivo_anulacion' => $motivo,
            ]);

            return $this->cargarCompra($compra->refresh());
        });
    }

    protected function validarDetallesDuplicados(array $detalles): void
    {
        $vistos = [];

        foreach ($detalles as $index => $detalle) {
            $loteKey = $detalle['lote_id'] ? 'lote:'.$detalle['lote_id'] : 'sin-lote';
            $key = $detalle['producto_id'].':'.$detalle['producto_presentacion_id'].':'.$loteKey;

            if (isset($vistos[$key])) {
                throw ValidationException::withMessages([
                    'detalles' => ['Producto duplicado en filas '.($vistos[$key] + 1).' y '.($index + 1).'. Ajuste la cantidad en una sola fila.'],
                ]);
            }

            $vistos[$key] = $index;
        }
    }

    public function calcularTotales(array $detalles): array
    {
        return [
            'subtotal' => round(collect($detalles)->sum('subtotal'), 2),
            'igv' => round(collect($detalles)->sum('igv'), 2),
            'descuento' => round(collect($detalles)->sum('descuento'), 2),
            'total' => round(collect($detalles)->sum('total'), 2),
        ];
    }

    protected function calcularDetalles(array $data): array
    {
        return collect($data['detalles'])->map(function (array $detalle) use ($data) {
            $producto = Producto::where('tenant_id', $data['tenant_id'])->where('empresa_id', $data['empresa_id'])->find($detalle['producto_id']);
            $presentacion = ProductoPresentacion::where('tenant_id', $data['tenant_id'])->where('empresa_id', $data['empresa_id'])->where('producto_id', $detalle['producto_id'])->find($detalle['producto_presentacion_id']);
            if (! $producto || ! $presentacion) {
                throw ValidationException::withMessages(['detalles' => ['Producto o presentacion invalidos.']]);
            }

            if ($producto->maneja_vencimiento && empty($detalle['fecha_vencimiento']) && empty($detalle['lote']['fecha_vencimiento']) && empty($detalle['lote_id'])) {
                throw ValidationException::withMessages(['detalles.*.fecha_vencimiento' => ['La fecha de vencimiento es obligatoria para este producto.']]);
            }

            $lote = $this->crearOResolverLote($data, $producto, $detalle);
            $cantidad = (float) $detalle['cantidad_presentacion'];
            $factor = (float) $presentacion->factor_conversion;
            $precio = (float) $detalle['precio_unitario'];
            $descuento = round((float) ($detalle['descuento'] ?? 0), 2);
            $bruto = round($cantidad * $precio, 2);
            if ($descuento > $bruto) {
                throw ValidationException::withMessages(['detalles.*.descuento' => ['El descuento no puede superar el importe del detalle.']]);
            }
            $total = round($bruto - $descuento, 2);
            $subtotal = $producto->afecto_igv ? round($total / (1 + self::IGV), 2) : $total;
            $igv = $producto->afecto_igv ? round($total - $subtotal, 2) : 0;

            return [
                'tenant_id' => $data['tenant_id'],
                'empresa_id' => $data['empresa_id'],
                'producto_id' => $producto->id,
                'producto_presentacion_id' => $presentacion->id,
                'lote_id' => $lote?->id,
                'descripcion' => trim($producto->nombre.' '.$presentacion->nombre),
                'cantidad_presentacion' => $cantidad,
                'factor_conversion' => $factor,
                'cantidad_base' => round($cantidad * $factor, 4),
                'precio_unitario' => $precio,
                'descuento' => $descuento,
                'afecto_igv' => $producto->afecto_igv,
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => $total,
                'fecha_vencimiento' => $detalle['fecha_vencimiento'] ?? $detalle['lote']['fecha_vencimiento'] ?? $lote?->fecha_vencimiento?->toDateString(),
            ];
        })->all();
    }

    public function crearOResolverLote(array $data, Producto $producto, array $detalle): ?Lote
    {
        if (! $producto->maneja_lote) {
            if (! empty($detalle['lote_id']) || ! empty($detalle['codigo_lote']) || ! empty($detalle['lote']['codigo_lote'])) {
                throw ValidationException::withMessages(['detalles.*.lote_id' => ['El producto no maneja lote.']]);
            }
            return null;
        }

        if (! empty($detalle['lote_id'])) {
            return Lote::where('tenant_id', $data['tenant_id'])->where('empresa_id', $data['empresa_id'])->where('producto_id', $producto->id)->findOrFail($detalle['lote_id']);
        }

        $codigoLote = $detalle['codigo_lote'] ?? $detalle['lote']['codigo_lote'] ?? null;
        $fechaVencimiento = $detalle['fecha_vencimiento'] ?? $detalle['lote']['fecha_vencimiento'] ?? null;
        if (! $codigoLote) {
            throw ValidationException::withMessages(['detalles.*.codigo_lote' => ['El lote es obligatorio para este producto.']]);
        }
        if ($producto->maneja_vencimiento && ! $fechaVencimiento) {
            throw ValidationException::withMessages(['detalles.*.fecha_vencimiento' => ['La fecha de vencimiento es obligatoria.']]);
        }

        return Lote::firstOrCreate([
            'empresa_id' => $data['empresa_id'],
            'producto_id' => $producto->id,
            'codigo_lote' => strtoupper(trim($codigoLote)),
        ], [
            'tenant_id' => $data['tenant_id'],
            'fecha_vencimiento' => $fechaVencimiento,
            'estado' => true,
        ]);
    }

    public function registrarEntradaInventario(Compra $compra, array $detalle, array $data): void
    {
        $this->inventarioService->aumentarStock([
            'tenant_id' => $data['tenant_id'],
            'empresa_id' => $data['empresa_id'],
            'tienda_id' => $data['tienda_id'],
            'producto_id' => $detalle['producto_id'],
            'producto_presentacion_id' => $detalle['producto_presentacion_id'],
            'lote_id' => $detalle['lote_id'],
            'cantidad_presentacion' => $detalle['cantidad_presentacion'],
            'motivo' => 'Compra '.$compra->serie.'-'.$compra->numero,
            'tipo_movimiento' => InventarioMovimiento::ENTRADA_COMPRA,
            'referencia_tipo' => 'COMPRA',
            'referencia_id' => $compra->id,
            'observacion' => $data['observacion'] ?? null,
            'user_id' => $data['user_id'],
        ]);
    }

    protected function cargarCompra(Compra $compra): Compra
    {
        return $compra->load([
            'proveedor',
            'tienda',
            'user',
            'creadoPor',
            'anuladoPor',
            'detalles.producto',
            'detalles.presentacion.unidadMedida',
            'detalles.lote',
            'movimientosInventario.producto',
            'movimientosInventario.presentacion.unidadMedida',
            'movimientosInventario.lote',
            'movimientosInventario.user',
            'cuentaPorPagar.proveedor',
        ]);
    }
}
