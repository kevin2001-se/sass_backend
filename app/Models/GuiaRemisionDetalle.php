<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuiaRemisionDetalle extends Model
{
    use HasFactory;

    protected $table = 'guia_remision_detalles';

    protected $fillable = [
        'tenant_id', 'empresa_id', 'guia_remision_id', 'producto_id', 'producto_presentacion_id',
        'descripcion', 'cantidad', 'unidad_medida', 'peso', 'codigo_producto',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'peso' => 'decimal:3',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function guiaRemision(): BelongsTo { return $this->belongsTo(GuiaRemision::class); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
    public function presentacion(): BelongsTo { return $this->belongsTo(ProductoPresentacion::class, 'producto_presentacion_id'); }
}