<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuentaPorCobrarPago extends Model
{
    use HasFactory;

    protected $table = 'cuentas_por_cobrar_pagos';

    protected $fillable = [
        'tenant_id', 'empresa_id', 'tienda_id', 'cuenta_por_cobrar_id', 'caja_id',
        'user_id', 'metodo_pago', 'monto', 'fecha_pago', 'referencia',
        'observacion', 'estado', 'anulado_by', 'anulado_at',
    ];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'fecha_pago' => 'date', 'anulado_at' => 'datetime'];
    }

    public function cuentaPorCobrar(): BelongsTo { return $this->belongsTo(CuentaPorCobrar::class); }
    public function caja(): BelongsTo { return $this->belongsTo(Caja::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function anuladoPor(): BelongsTo { return $this->belongsTo(User::class, 'anulado_by'); }
}
