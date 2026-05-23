<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CajaMovimiento extends Model
{
    use HasFactory;

    public const APERTURA = 'APERTURA';
    public const INGRESO = 'INGRESO';
    public const EGRESO = 'EGRESO';
    public const VENTA = 'VENTA';
    public const ANULACION_VENTA = 'ANULACION_VENTA';
    public const AJUSTE = 'AJUSTE';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'caja_id',
        'user_id',
        'tipo_movimiento',
        'metodo_pago',
        'concepto',
        'monto',
        'referencia_tipo',
        'referencia_id',
        'observacion',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
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

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
