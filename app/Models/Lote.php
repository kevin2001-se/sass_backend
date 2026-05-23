<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lote extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'producto_id',
        'codigo_lote',
        'fecha_vencimiento',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'estado' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
    public function stocks(): HasMany { return $this->hasMany(Stock::class); }
    public function movimientos(): HasMany { return $this->hasMany(InventarioMovimiento::class); }
    public function ventaDetalles(): HasMany { return $this->hasMany(VentaDetalle::class); }
    public function compraDetalles(): HasMany { return $this->hasMany(CompraDetalle::class); }
}
