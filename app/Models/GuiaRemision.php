<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GuiaRemision extends Model
{
    use HasFactory;

    public const BORRADOR = 'BORRADOR';
    public const REGISTRADA = 'REGISTRADA';
    public const ANULADA = 'ANULADA';

    public const SUNAT_PENDIENTE = 'PENDIENTE';
    public const SUNAT_ENVIADO = 'ENVIADO';
    public const SUNAT_ACEPTADO = 'ACEPTADO';
    public const SUNAT_RECHAZADO = 'RECHAZADO';
    public const SUNAT_ERROR = 'ERROR';

    protected $table = 'guias_remision';

    protected $fillable = [
        'tenant_id', 'empresa_id', 'tienda_id', 'venta_id', 'comprobante_id', 'tipo_referencia', 'referencia_serie', 'referencia_numero', 'compra_id', 'cliente_id', 'proveedor_id',
        'serie', 'correlativo', 'numero_completo', 'numero_guia', 'fecha_emision', 'fecha_traslado',
        'motivo_traslado_codigo', 'motivo_traslado_descripcion', 'modalidad_transporte',
        'destinatario_tipo_documento', 'destinatario_numero_documento', 'destinatario_nombre',
        'peso_total', 'unidad_peso', 'numero_bultos', 'punto_partida_ubigeo', 'punto_partida_direccion',
        'punto_llegada_ubigeo', 'punto_llegada_direccion', 'transportista_tipo_documento',
        'transportista_numero_documento', 'transportista_ruc', 'transportista_razon_social',
        'conductor_tipo_documento', 'conductor_numero_documento', 'conductor_nombre',
        'conductor_licencia', 'vehiculo_placa', 'estado', 'observacion', 'xml_path', 'cdr_path',
        'hash', 'qr_text', 'estado_sunat', 'codigo_respuesta', 'mensaje_respuesta',
        'intentos_envio', 'enviado_at', 'aceptado_at', 'rechazado_at', 'pdf_a4_path',
        'ticket_80_path', 'pdf_generado_at', 'ticket_generado_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'fecha_traslado' => 'date',
            'correlativo' => 'integer',
            'peso_total' => 'decimal:3',
            'numero_bultos' => 'integer',
            'intentos_envio' => 'integer',
            'enviado_at' => 'datetime',
            'aceptado_at' => 'datetime',
            'rechazado_at' => 'datetime',
            'pdf_generado_at' => 'datetime',
            'ticket_generado_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function tienda(): BelongsTo { return $this->belongsTo(Tienda::class); }
    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function comprobante(): BelongsTo { return $this->belongsTo(ComprobanteElectronico::class, 'comprobante_id'); }
    public function compra(): BelongsTo { return $this->belongsTo(Compra::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function motivoTraslado(): BelongsTo { return $this->belongsTo(MotivoTraslado::class, 'motivo_traslado_codigo', 'codigo'); }
    public function modalidadTransporte(): BelongsTo { return $this->belongsTo(ModalidadTransporte::class, 'modalidad_transporte', 'codigo'); }
    public function detalles(): HasMany { return $this->hasMany(GuiaRemisionDetalle::class); }
    public function documentosRelacionados(): HasMany { return $this->hasMany(GuiaRemisionDocumentoRelacionado::class); }
    public function comprobanteElectronico(): HasOne { return $this->hasOne(ComprobanteElectronico::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}