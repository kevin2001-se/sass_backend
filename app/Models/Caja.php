<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caja extends Model
{
    use HasFactory;

    public const ABIERTA = 'ABIERTA';
    public const CERRADA = 'CERRADA';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'user_apertura_id',
        'user_cierre_id',
        'fecha_apertura',
        'fecha_cierre',
        'monto_apertura',
        'monto_cierre_sistema',
        'monto_cierre_real',
        'diferencia',
        'estado',
        'observacion_apertura',
        'observacion_cierre',
    ];

    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'datetime',
            'fecha_cierre' => 'datetime',
            'monto_apertura' => 'decimal:2',
            'monto_cierre_sistema' => 'decimal:2',
            'monto_cierre_real' => 'decimal:2',
            'diferencia' => 'decimal:2',
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

    public function userApertura(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_apertura_id');
    }

    public function userCierre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_cierre_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class);
    }
}
