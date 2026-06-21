<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComunicacionBajaDetalle extends Model
{
    use HasFactory;

    protected $table = 'comunicacion_baja_detalles';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'comunicacion_baja_id',
        'comprobante_id',
        'comprobante_electronico_id',
        'tipo_documento',
        'serie',
        'correlativo',
        'numero_comprobante',
        'numero_completo',
        'motivo_baja',
    ];

    protected function casts(): array
    {
        return [
            'correlativo' => 'integer',
            'comprobante_id' => 'integer',
            'comprobante_electronico_id' => 'integer',
        ];
    }

    public function comunicacionBaja(): BelongsTo { return $this->belongsTo(ComunicacionBaja::class); }
    public function comprobante(): BelongsTo { return $this->belongsTo(ComprobanteElectronico::class, 'comprobante_id'); }
    public function comprobanteElectronico(): BelongsTo { return $this->belongsTo(ComprobanteElectronico::class, 'comprobante_electronico_id'); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
}

