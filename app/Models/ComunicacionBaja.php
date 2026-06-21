<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComunicacionBaja extends Model
{
    use HasFactory;

    public const REGISTRADA = 'REGISTRADA';
    public const ANULADA = 'ANULADA';

    public const PENDIENTE = 'PENDIENTE';
    public const ENVIADO = 'ENVIADO';
    public const ACEPTADO = 'ACEPTADO';
    public const RECHAZADO = 'RECHAZADO';
    public const ERROR = 'ERROR';

    protected $table = 'comunicaciones_baja';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'fecha_baja',
        'fecha_envio',
        'identificador',
        'correlativo',
        'estado',
        'estado_sunat',
        'ticket',
        'ticket_sunat',
        'xml_path',
        'cdr_path',
        'hash',
        'codigo_respuesta',
        'mensaje_respuesta',
        'intentos_envio',
        'enviado_at',
        'consultado_at',
        'aceptado_at',
        'rechazado_at',
        'total_documentos',
        'observacion',
        'created_by',
        'updated_by',
        'anulado_by',
        'anulado_at',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_baja' => 'date',
            'fecha_envio' => 'date',
            'correlativo' => 'integer',
            'total_documentos' => 'integer',
            'intentos_envio' => 'integer',
            'enviado_at' => 'datetime',
            'consultado_at' => 'datetime',
            'aceptado_at' => 'datetime',
            'rechazado_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function tienda(): BelongsTo { return $this->belongsTo(Tienda::class); }
    public function detalles(): HasMany { return $this->hasMany(ComunicacionBajaDetalle::class); }
    public function creadoPor(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function actualizadoPor(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function anuladoPor(): BelongsTo { return $this->belongsTo(User::class, 'anulado_by'); }
}
