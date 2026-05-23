<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuentaPorPagarPago extends Model
{
    use HasFactory;

    protected $table = 'cuentas_por_pagar_pagos';

    protected $fillable = ['tenant_id', 'empresa_id', 'tienda_id', 'cuenta_por_pagar_id', 'caja_id', 'user_id', 'metodo_pago', 'monto', 'fecha_pago', 'referencia', 'observacion', 'estado'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'fecha_pago' => 'date'];
    }

    public function cuentaPorPagar(): BelongsTo { return $this->belongsTo(CuentaPorPagar::class); }
    public function caja(): BelongsTo { return $this->belongsTo(Caja::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
