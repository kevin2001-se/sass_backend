<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'producto_id',
        'lote_id',
        'cantidad_actual',
        'cantidad_minima',
        'cantidad_maxima',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_actual' => 'decimal:4',
            'cantidad_minima' => 'decimal:4',
            'cantidad_maxima' => 'decimal:4',
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

    public function tienda(): BelongsTo
    {
        return $this->belongsTo(Tienda::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'producto_id', 'producto_id');
    }
}
