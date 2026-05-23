<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NotaElectronica extends Model
{
    use HasFactory;

    public const NOTA_CREDITO = 'NOTA_CREDITO';
    public const NOTA_DEBITO = 'NOTA_DEBITO';
    public const REGISTRADA = 'REGISTRADA';
    public const ANULADA = 'ANULADA';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'venta_id',
        'comprobante_referencia_id',
        'tipo_nota',
        'serie',
        'correlativo',
        'numero_comprobante',
        'motivo_codigo',
        'motivo_descripcion',
        'fecha_emision',
        'moneda',
        'subtotal',
        'total_igv',
        'total',
        'estado',
        'afecta_stock',
        'afecta_caja',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'subtotal' => 'decimal:2',
            'total_igv' => 'decimal:2',
            'total' => 'decimal:2',
            'afecta_stock' => 'boolean',
            'afecta_caja' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function tienda(): BelongsTo { return $this->belongsTo(Tienda::class); }
    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function comprobanteReferencia(): BelongsTo { return $this->belongsTo(ComprobanteElectronico::class, 'comprobante_referencia_id'); }
    public function detalles(): HasMany { return $this->hasMany(NotaDetalle::class); }
    public function comprobanteElectronico(): HasOne { return $this->hasOne(ComprobanteElectronico::class); }
}
