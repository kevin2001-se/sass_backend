<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoPresentacion extends Model
{
    use HasFactory;

    protected $table = 'producto_presentaciones';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'producto_id',
        'unidad_medida_id',
        'nombre',
        'codigo_barra',
        'factor_conversion',
        'precio_compra',
        'precio_venta',
        'es_principal',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'factor_conversion' => 'decimal:4',
            'precio_compra' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'es_principal' => 'boolean',
            'estado' => 'boolean',
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

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class);
    }

    public function ventaDetalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function compraDetalles(): HasMany { return $this->hasMany(CompraDetalle::class); }
}
