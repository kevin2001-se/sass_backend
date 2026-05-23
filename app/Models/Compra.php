<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Compra extends Model
{
    use HasFactory;

    public const CONTADO = 'CONTADO';
    public const CREDITO = 'CREDITO';
    public const REGISTRADA = 'REGISTRADA';
    public const ANULADA = 'ANULADA';

    protected $fillable = ['tenant_id', 'empresa_id', 'tienda_id', 'proveedor_id', 'user_id', 'tipo_comprobante', 'serie', 'numero', 'tipo_compra', 'fecha_emision', 'fecha_vencimiento', 'subtotal', 'total_igv', 'total_descuento', 'total', 'estado', 'observacion'];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
            'subtotal' => 'decimal:2',
            'total_igv' => 'decimal:2',
            'total_descuento' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function tienda(): BelongsTo { return $this->belongsTo(Tienda::class); }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function detalles(): HasMany { return $this->hasMany(CompraDetalle::class); }
    public function pagos(): HasMany { return $this->hasMany(CompraPago::class); }
    public function cuentaPorPagar(): HasOne { return $this->hasOne(CuentaPorPagar::class); }
    public function guiasRemision(): HasMany { return $this->hasMany(GuiaRemision::class); }
}
