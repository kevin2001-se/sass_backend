<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaPorCobrar extends Model
{
    use HasFactory;

    protected $table = 'cuentas_por_cobrar';

    public const PENDIENTE = 'PENDIENTE';
    public const PARCIAL = 'PARCIAL';
    public const PAGADA = 'PAGADA';
    public const VENCIDA = 'VENCIDA';
    public const ANULADA = 'ANULADA';

    protected $fillable = [
        'tenant_id', 'empresa_id', 'tienda_id', 'cliente_id', 'venta_id',
        'monto_total', 'monto_pagado', 'saldo', 'fecha_emision',
        'fecha_vencimiento', 'estado', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
            'saldo' => 'decimal:2',
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function tienda(): BelongsTo { return $this->belongsTo(Tienda::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function pagos(): HasMany { return $this->hasMany(CuentaPorCobrarPago::class); }
}
