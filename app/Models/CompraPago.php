<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraPago extends Model
{
    use HasFactory;

    protected $table = 'compra_pagos';

    protected $fillable = ['tenant_id', 'empresa_id', 'compra_id', 'metodo_pago', 'monto', 'referencia', 'estado'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    public function compra(): BelongsTo { return $this->belongsTo(Compra::class); }
}
