<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaPorPagar extends Model
{
    use HasFactory;

    protected $table = 'cuentas_por_pagar';

    public const PENDIENTE = 'PENDIENTE';
    public const PARCIAL = 'PARCIAL';
    public const PAGADO = 'PAGADO';
    public const PAGADA = 'PAGADA';
    public const VENCIDO = 'VENCIDO';
    public const ANULADO = 'ANULADO';
    public const ANULADA = 'ANULADA';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'proveedor_id',
        'compra_id',
        'fecha_emision',
        'fecha_vencimiento',
        'monto_total',
        'monto_pagado',
        'saldo',
        'estado',
        'observacion',
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
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function compra(): BelongsTo { return $this->belongsTo(Compra::class); }
    public function pagos(): HasMany { return $this->hasMany(PagoProveedor::class, 'cuenta_por_pagar_id'); }
}