<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResumenDiario extends Model
{
    use HasFactory;

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
        'estado_sunat',
        'ticket',
        'xml_path',
        'cdr_path',
        'codigo_respuesta',
        'mensaje_respuesta',
        'intentos_envio',
        'enviado_at',
        'aceptado_at',
        'rechazado_at',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_resumen' => 'date',
            'fecha_envio' => 'date',
            'correlativo' => 'integer',
            'intentos_envio' => 'integer',
            'enviado_at' => 'datetime',
            'aceptado_at' => 'datetime',
            'rechazado_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function tienda(): BelongsTo { return $this->belongsTo(Tienda::class); }
    public function detalles(): HasMany { return $this->hasMany(ResumenDiarioDetalle::class); }
}
