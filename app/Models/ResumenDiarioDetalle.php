<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumenDiarioDetalle extends Model
{
    use HasFactory;

    public const ADICIONAR = '1';
    public const MODIFICAR = '2';
    public const ANULAR = '3';

    protected $table = 'resumen_diario_detalles';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'resumen_diario_id',
        'comprobante_electronico_id',
        'tipo_documento',
        'serie',
        'correlativo',
        'numero_comprobante',
        'estado_item',
        'total',
        'total_igv',
    ];

    protected function casts(): array
    {
        return [
            'correlativo' => 'integer',
            'total' => 'decimal:2',
            'total_igv' => 'decimal:2',
        ];
    }

    public function resumenDiario(): BelongsTo { return $this->belongsTo(ResumenDiario::class); }
    public function comprobanteElectronico(): BelongsTo { return $this->belongsTo(ComprobanteElectronico::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function tienda(): BelongsTo { return $this->belongsTo(Tienda::class); }
}
