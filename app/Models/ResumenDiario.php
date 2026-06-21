<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResumenDiario extends Model
{
    use HasFactory;

    public const BORRADOR = 'BORRADOR';
    public const REGISTRADO = 'REGISTRADO';
    public const ANULADO = 'ANULADO';

    public const PENDIENTE = 'PENDIENTE';
    public const ENVIADO = 'ENVIADO';
    public const ACEPTADO = 'ACEPTADO';
    public const RECHAZADO = 'RECHAZADO';
    public const ERROR = 'ERROR';

    protected $table = 'resumenes_diarios';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'fecha_resumen',
        'fecha_envio',
        'correlativo',
        'identificador',
        'estado',
        'estado_sunat',
        'total_documentos',
        'total_boletas',
        'total_notas_credito',
        'total_notas_debito',
        'monto_total',
        'ticket',
        'ticket_sunat',
        'xml_path',
        'cdr_path',
        'pdf_a4_path',
        'pdf_generado_at',
        'hash',
        'codigo_respuesta',
        'mensaje_respuesta',
        'intentos_envio',
        'enviado_at',
        'consultado_at',
        'aceptado_at',
        'rechazado_at',
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
            'fecha_resumen' => 'date',
            'fecha_envio' => 'date',
            'correlativo' => 'integer',
            'total_documentos' => 'integer',
            'total_boletas' => 'integer',
            'total_notas_credito' => 'integer',
            'total_notas_debito' => 'integer',
            'monto_total' => 'decimal:2',
            'intentos_envio' => 'integer',
            'enviado_at' => 'datetime',
            'consultado_at' => 'datetime',
            'pdf_generado_at' => 'datetime',
            'aceptado_at' => 'datetime',
            'rechazado_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function tienda(): BelongsTo { return $this->belongsTo(Tienda::class); }
    public function detalles(): HasMany { return $this->hasMany(ResumenDiarioDetalle::class); }
    public function creadoPor(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function actualizadoPor(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function anuladoPor(): BelongsTo { return $this->belongsTo(User::class, 'anulado_by'); }
}
