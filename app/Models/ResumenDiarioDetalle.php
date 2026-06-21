<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumenDiarioDetalle extends Model
{
    use HasFactory;

    public const BOLETA = 'BOLETA';
    public const NOTA_CREDITO = 'NOTA_CREDITO';
    public const NOTA_DEBITO = 'NOTA_DEBITO';

    public const ADICIONAR = '1';
    public const MODIFICAR = '2';
    public const ANULAR = '3';

    public const ACCION_ALTA = 'ALTA';
    public const ACCION_BAJA = 'BAJA';

    protected $table = 'resumen_diario_detalles';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'resumen_diario_id',
        'documento_id',
        'comprobante_electronico_id',
        'tipo_documento',
        'serie',
        'correlativo',
        'numero_comprobante',
        'numero_completo',
        'cliente_tipo_documento',
        'cliente_numero_documento',
        'cliente_nombre',
        'subtotal',
        'estado_item',
        'accion',
        'total',
        'total_igv',
        'estado_documento',
        'estado_baja',
        'motivo_baja',
    ];

    protected function casts(): array
    {
        return [
            'documento_id' => 'integer',
            'comprobante_electronico_id' => 'integer',
            'correlativo' => 'integer',
            'subtotal' => 'decimal:2',
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
