<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaDetalle extends Model
{
    use HasFactory;

    protected $table = 'nota_detalles';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'nota_electronica_id',
        'venta_detalle_id',
        'producto_id',
        'producto_presentacion_id',
        'lote_id',
        'descripcion',
        'cantidad_presentacion',
        'factor_conversion',
        'cantidad_base',
        'precio_unitario',
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
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function notaElectronica(): BelongsTo { return $this->belongsTo(NotaElectronica::class); }
    public function ventaDetalle(): BelongsTo { return $this->belongsTo(VentaDetalle::class); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
    public function presentacion(): BelongsTo { return $this->belongsTo(ProductoPresentacion::class, 'producto_presentacion_id'); }
    public function lote(): BelongsTo { return $this->belongsTo(Lote::class); }
}
