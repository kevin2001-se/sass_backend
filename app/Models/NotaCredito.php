<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaCredito extends Model
{
    use HasFactory;

    public const TOTAL = 'TOTAL';
    public const PARCIAL = 'PARCIAL';
    public const BORRADOR = 'BORRADOR';
    public const REGISTRADA = 'REGISTRADA';
    public const ANULADA = 'ANULADA';
    public const SUNAT_PENDIENTE = 'PENDIENTE';
    public const SUNAT_ENVIADO = 'ENVIADO';
    public const SUNAT_ACEPTADO = 'ACEPTADO';
    public const SUNAT_RECHAZADO = 'RECHAZADO';
    public const SUNAT_ERROR = 'ERROR';

    protected $table = 'notas_credito';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'venta_id',
        'comprobante_id',
        'serie',
        'correlativo',
        'numero_completo',
        'motivo_codigo',
        'motivo_descripcion',
        'tipo_nota',
        'afecta_stock',
        'afecta_caja',
        'stock_aplicado',
        'caja_aplicada',
        'stock_aplicado_at',
        'caja_aplicada_at',
        'caja_movimiento_id',
        'subtotal',
        'total_descuento',
        'total_igv',
        'total',
        'observacion',
        'motivo_anulacion',
        'xml_path',
        'cdr_path',
        'pdf_a4_path',
        'ticket_80_path',
        'hash',
        'qr_text',
        'estado_sunat',
        'codigo_respuesta',
        'mensaje_respuesta',
        'intentos_envio',
        'enviado_at',
        'aceptado_at',
        'rechazado_at',
        'pdf_generado_at',
        'ticket_generado_at',
        'estado',
        'created_by',
        'updated_by',
        'anulado_by',
        'anulado_at',
    ];

    protected function casts(): array
    {
        return [
            'afecta_stock' => 'boolean',
            'afecta_caja' => 'boolean',
            'stock_aplicado' => 'boolean',
            'caja_aplicada' => 'boolean',
            'stock_aplicado_at' => 'datetime',
            'caja_aplicada_at' => 'datetime',
            'intentos_envio' => 'integer',
            'enviado_at' => 'datetime',
            'aceptado_at' => 'datetime',
            'rechazado_at' => 'datetime',
            'pdf_generado_at' => 'datetime',
            'ticket_generado_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'total_descuento' => 'decimal:2',
            'total_igv' => 'decimal:2',
            'total' => 'decimal:2',
            'anulado_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function tienda(): BelongsTo { return $this->belongsTo(Tienda::class); }
    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function comprobante(): BelongsTo { return $this->belongsTo(ComprobanteElectronico::class, 'comprobante_id'); }
    public function motivo(): BelongsTo { return $this->belongsTo(MotivoNotaCredito::class, 'motivo_codigo', 'codigo'); }
    public function detalles(): HasMany { return $this->hasMany(NotaCreditoDetalle::class); }
    public function cajaMovimiento(): BelongsTo { return $this->belongsTo(CajaMovimiento::class); }
    public function creadoPor(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function actualizadoPor(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function anuladoPor(): BelongsTo { return $this->belongsTo(User::class, 'anulado_by'); }
}
