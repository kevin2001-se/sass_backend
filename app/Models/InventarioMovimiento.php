<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioMovimiento extends Model
{
    use HasFactory;

    public const ENTRADA = 'ENTRADA';
    public const SALIDA = 'SALIDA';
    public const AJUSTE_POSITIVO = 'AJUSTE_POSITIVO';
    public const AJUSTE_NEGATIVO = 'AJUSTE_NEGATIVO';
    public const VENTA = 'VENTA';
    public const COMPRA = 'COMPRA';
    public const DEVOLUCION = 'DEVOLUCION';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'producto_id',
        'producto_presentacion_id',
        'lote_id',
        'tipo_movimiento',
        'motivo',
        'cantidad_presentacion',
        'factor_conversion',
        'cantidad_base',
        'stock_anterior',
        'stock_nuevo',
        'referencia_tipo',
        'referencia_id',
        'observacion',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_presentacion' => 'decimal:4',
            'factor_conversion' => 'decimal:4',
            'cantidad_base' => 'decimal:4',
            'stock_anterior' => 'decimal:4',
            'stock_nuevo' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function tienda(): BelongsTo
    {
        return $this->belongsTo(Tienda::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(ProductoPresentacion::class, 'producto_presentacion_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
