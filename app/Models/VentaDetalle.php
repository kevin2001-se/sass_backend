<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaDetalle extends Model
{
    use HasFactory;

    protected $table = 'venta_detalles';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'venta_id',
        'producto_id',
        'producto_presentacion_id',
        'lote_id',
        'descripcion',
        'cantidad_presentacion',
        'factor_conversion',
        'cantidad_base',
        'precio_unitario',
        'descuento',
        'afecto_igv',
        'subtotal',
        'igv',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_presentacion' => 'decimal:4',
            'factor_conversion' => 'decimal:4',
            'cantidad_base' => 'decimal:4',
            'precio_unitario' => 'decimal:2',
            'descuento' => 'decimal:2',
            'afecto_igv' => 'boolean',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
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
}
