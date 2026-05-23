<?php

namespace App\Services;

use App\Models\InventarioMovimiento;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Stock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioService
{
    public function aumentarStock(array $data): InventarioMovimiento
    {
        return DB::transaction(function () use ($data) {
            return $this->moverStock($data, InventarioMovimiento::ENTRADA, 'aumentar');
        });
    }

    public function disminuirStock(array $data): InventarioMovimiento
    {
        return DB::transaction(function () use ($data) {
            return $this->moverStock($data, InventarioMovimiento::SALIDA, 'disminuir');
        });
    }

    public function ajustarStock(array $data): InventarioMovimiento
    {
        return DB::transaction(function () use ($data) {
            $tipoAjuste = $data['tipo_ajuste'] ?? null;
            $tipoMovimiento = $tipoAjuste === 'POSITIVO'
                ? InventarioMovimiento::AJUSTE_POSITIVO
                : InventarioMovimiento::AJUSTE_NEGATIVO;
            $operacion = $tipoAjuste === 'POSITIVO' ? 'aumentar' : 'disminuir';

            return $this->moverStock($data, $tipoMovimiento, $operacion);
        });
    }

    public function obtenerStockProducto(int $productoId, int $tiendaId, int $empresaId, int $tenantId): Collection
    {
        return Stock::with(['producto', 'lote'])
            ->where('tenant_id', $tenantId)
            ->where('empresa_id', $empresaId)
            ->where('tienda_id', $tiendaId)
            ->where('producto_id', $productoId)
            ->orderByRaw('lote_id IS NULL DESC')
            ->get()
            ->sortBy(fn (Stock $stock) => $stock->lote?->fecha_vencimiento?->toDateString() ?? '9999-12-31')
            ->values();
    }

    public function obtenerKardexProducto(int $productoId, int $tiendaId, int $empresaId, int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return InventarioMovimiento::with(['producto', 'presentacion.unidadMedida', 'lote', 'user'])
            ->where('tenant_id', $tenantId)
            ->where('empresa_id', $empresaId)
            ->where('tienda_id', $tiendaId)
            ->where('producto_id', $productoId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    protected function moverStock(array $data, string $tipoMovimiento, string $operacion): InventarioMovimiento
    {
        $producto = $this->obtenerProducto($data);
        $presentacion = $this->obtenerPresentacion($data, $producto);
        $lote = $this->obtenerLote($data, $producto);

        if ($operacion === 'disminuir') {
            $this->validarLoteVigenteParaSalida($lote);
        }

        $cantidadPresentacion = (float) $data['cantidad_presentacion'];
        $factorConversion = (float) $presentacion->factor_conversion;
        $cantidadBase = round($cantidadPresentacion * $factorConversion, 4);

        if ($cantidadBase <= 0) {
            throw ValidationException::withMessages([
                'cantidad_presentacion' => ['La cantidad base calculada debe ser mayor a 0.'],
            ]);
        }

        $stock = $this->obtenerStockBloqueado($data, $producto, $lote);
        $stockAnterior = (float) $stock->cantidad_actual;
        $stockNuevo = $operacion === 'aumentar'
            ? $stockAnterior + $cantidadBase
            : $stockAnterior - $cantidadBase;

        if ($stockNuevo < 0) {
            throw ValidationException::withMessages([
                'cantidad_presentacion' => ['No hay stock suficiente. El stock no puede quedar negativo.'],
            ]);
        }

        $stock->update([
            'cantidad_actual' => $stockNuevo,
            'estado' => true,
        ]);

        return InventarioMovimiento::create([
            'tenant_id' => $data['tenant_id'],
            'empresa_id' => $data['empresa_id'],
            'tienda_id' => $data['tienda_id'],
            'producto_id' => $producto->id,
            'producto_presentacion_id' => $presentacion->id,
            'lote_id' => $lote?->id,
            'tipo_movimiento' => $data['tipo_movimiento'] ?? $tipoMovimiento,
            'motivo' => $data['motivo'],
            'cantidad_presentacion' => $cantidadPresentacion,
            'factor_conversion' => $factorConversion,
            'cantidad_base' => $cantidadBase,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo' => $stockNuevo,
            'referencia_tipo' => $data['referencia_tipo'] ?? null,
            'referencia_id' => $data['referencia_id'] ?? null,
            'observacion' => $data['observacion'] ?? null,
            'user_id' => $data['user_id'],
            'created_at' => now(),
        ]);
    }

    protected function obtenerProducto(array $data): Producto
    {
        $producto = Producto::where('tenant_id', $data['tenant_id'])
            ->where('empresa_id', $data['empresa_id'])
            ->find($data['producto_id']);

        if (! $producto) {
            throw ValidationException::withMessages([
                'producto_id' => ['El producto no pertenece a la empresa indicada.'],
            ]);
        }

        return $producto;
    }

    protected function obtenerPresentacion(array $data, Producto $producto): ProductoPresentacion
    {
        $presentacion = ProductoPresentacion::where('tenant_id', $data['tenant_id'])
            ->where('empresa_id', $data['empresa_id'])
            ->where('producto_id', $producto->id)
            ->find($data['producto_presentacion_id']);

        if (! $presentacion) {
            throw ValidationException::withMessages([
                'producto_presentacion_id' => ['La presentaciÃ³n no pertenece al producto indicado.'],
            ]);
        }

        return $presentacion;
    }

    protected function obtenerLote(array $data, Producto $producto): ?Lote
    {
        $loteId = $data['lote_id'] ?? null;

        if ($producto->maneja_lote && ! $loteId) {
            throw ValidationException::withMessages([
                'lote_id' => ['El lote es obligatorio para este producto.'],
            ]);
        }

        if (! $producto->maneja_lote && $loteId) {
            throw ValidationException::withMessages([
                'lote_id' => ['El lote debe ser null porque el producto no maneja lotes.'],
            ]);
        }

        if (! $loteId) {
            return null;
        }

        $lote = Lote::where('tenant_id', $data['tenant_id'])
            ->where('empresa_id', $data['empresa_id'])
            ->where('producto_id', $producto->id)
            ->find($loteId);

        if (! $lote) {
            throw ValidationException::withMessages([
                'lote_id' => ['El lote no pertenece al producto o empresa indicada.'],
            ]);
        }

        if ($producto->maneja_vencimiento && ! $lote->fecha_vencimiento) {
            throw ValidationException::withMessages([
                'lote_id' => ['El lote debe tener fecha de vencimiento para este producto.'],
            ]);
        }

        return $lote;
    }

    protected function validarLoteVigenteParaSalida(?Lote $lote): void
    {
        if (! $lote?->fecha_vencimiento) {
            return;
        }

        if ($lote->fecha_vencimiento->lt(today())) {
            throw ValidationException::withMessages([
                'lote_id' => ['No se puede retirar stock de un lote vencido. Use FEFO seleccionando primero lotes vigentes con vencimiento mÃ¡s prÃ³ximo.'],
            ]);
        }
    }

    protected function obtenerStockBloqueado(array $data, Producto $producto, ?Lote $lote): Stock
    {
        $query = Stock::where('tenant_id', $data['tenant_id'])
            ->where('empresa_id', $data['empresa_id'])
            ->where('tienda_id', $data['tienda_id'])
            ->where('producto_id', $producto->id);

        $lote ? $query->where('lote_id', $lote->id) : $query->whereNull('lote_id');

        $stock = $query->lockForUpdate()->first();

        if ($stock) {
            return $stock;
        }

        return Stock::create([
            'tenant_id' => $data['tenant_id'],
            'empresa_id' => $data['empresa_id'],
            'tienda_id' => $data['tienda_id'],
            'producto_id' => $producto->id,
            'lote_id' => $lote?->id,
            'cantidad_actual' => 0,
            'estado' => true,
        ]);
    }
}



