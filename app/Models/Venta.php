<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Venta extends Model
{
    use HasFactory;

    public const NOTA_VENTA = 'NOTA_VENTA';
    public const BOLETA = 'BOLETA';
    public const FACTURA = 'FACTURA';
    public const CONTADO = 'CONTADO';
    public const CREDITO = 'CREDITO';
    public const REGISTRADA = 'REGISTRADA';
    public const ANULADA = 'ANULADA';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'cliente_id',
        'user_id',
        'tipo_comprobante',
        'serie',
        'correlativo',
        'numero_comprobante',
        'tipo_venta',
        'fecha_emision',
        'subtotal',
        'total_igv',
        'total_descuento',
        'total',
        'estado',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'subtotal' => 'decimal:2',
            'total_igv' => 'decimal:2',
            'total_descuento' => 'decimal:2',
            'total' => 'decimal:2',
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

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class);
    }

    public function cuentaPorCobrar(): HasOne
    {
        return $this->hasOne(CuentaPorCobrar::class);
    }

    public function comprobanteElectronico(): HasOne
    {
        return $this->hasOne(ComprobanteElectronico::class);
    }

    public function notasElectronicas(): HasMany
    {
        return $this->hasMany(NotaElectronica::class);
    }

    public function guiasRemision(): HasMany
    {
        return $this->hasMany(GuiaRemision::class);
    }
}
