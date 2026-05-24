<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaCreditoDetalle extends Model
{
    use HasFactory;

    protected $table = 'nota_credito_detalles';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'nota_credito_id',
        'venta_detalle_id',
        'producto_id',
        'descripcion',
        'unidad_medida',
        'cantidad',
        'precio_unitario',
        'descuento',
        'subtotal',
        'igv',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'precio_unitario' => 'decimal:2',
            'descuento' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function notaCredito(): BelongsTo { return $this->belongsTo(NotaCredito::class); }
    public function ventaDetalle(): BelongsTo { return $this->belongsTo(VentaDetalle::class); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
}
