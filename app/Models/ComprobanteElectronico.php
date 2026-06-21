<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComprobanteElectronico extends Model
{
    use HasFactory;

    protected $table = 'comprobantes_electronicos';

    public const PENDIENTE = 'PENDIENTE';
    public const ENVIADO = 'ENVIADO';
    public const ACEPTADO = 'ACEPTADO';
    public const RECHAZADO = 'RECHAZADO';
    public const ERROR = 'ERROR';
    public const DADO_DE_BAJA = 'DADO_DE_BAJA';

    public const BAJA_SIN_BAJA = 'SIN_BAJA';
    public const BAJA_PENDIENTE = 'PENDIENTE_BAJA';
    public const BAJA_EN_BAJA = 'EN_BAJA';
    public const BAJA_ACEPTADA = 'BAJA_ACEPTADA';
    public const BAJA_RECHAZADA = 'BAJA_RECHAZADA';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'venta_id',
        'nota_electronica_id',
        'comunicacion_baja_id',
        'guia_remision_id',
        'documento_origen_tipo',
        'documento_origen_id',
        'tipo_comprobante',
        'serie',
        'correlativo',
        'numero_comprobante',
        'fecha_emision',
        'moneda',
        'xml_path',
        'cdr_path',
        'pdf_a4_path',
        'ticket_80_path',
        'ticket_58_path',
        'hash',
        'qr_text',
        'estado_sunat',
        'estado_baja',
        'motivo_baja',
        'fecha_solicitud_baja',
        'solicitado_baja_por',
        'codigo_respuesta',
        'mensaje_respuesta',
        'ticket',
        'intentos_envio',
        'enviado_at',
        'aceptado_at',
        'rechazado_at',
        'dado_baja_at',
        'pdf_generado_at',
        'ticket_generado_at',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'intentos_envio' => 'integer',
            'enviado_at' => 'datetime',
            'aceptado_at' => 'datetime',
            'rechazado_at' => 'datetime',
            'dado_baja_at' => 'datetime',
            'fecha_solicitud_baja' => 'datetime',
            'pdf_generado_at' => 'datetime',
            'ticket_generado_at' => 'datetime',
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

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function notaElectronica(): BelongsTo
    {
        return $this->belongsTo(NotaElectronica::class);
    }

    public function resumenDiarioDetalles(): HasMany
    {
        return $this->hasMany(ResumenDiarioDetalle::class);
    }

    public function comunicacionBaja(): BelongsTo
    {
        return $this->belongsTo(ComunicacionBaja::class);
    }

    public function comunicacionBajaDetalles(): HasMany
    {
        return $this->hasMany(ComunicacionBajaDetalle::class);
    }

    public function guiaRemision(): BelongsTo
    {
        return $this->belongsTo(GuiaRemision::class);
    }

    public function notasCredito(): HasMany
    {
        return $this->hasMany(NotaCredito::class, 'comprobante_id');
    }
    public function notasDebito(): HasMany
    {
        return $this->hasMany(NotaDebito::class, 'comprobante_id');
    }

    public function bajaHistorial(): HasMany
    {
        return $this->hasMany(ComprobanteBajaHistorial::class, 'comprobante_id');
    }

    public function solicitadoBajaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_baja_por');
    }
}




