<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuiaRemisionDocumentoRelacionado extends Model
{
    use HasFactory;

    protected $table = 'guia_remision_documentos_relacionados';

    protected $fillable = [
        'tenant_id', 'empresa_id', 'guia_remision_id', 'tipo_documento', 'serie', 'numero',
        'comprobante_electronico_id', 'venta_id', 'compra_id',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function guiaRemision(): BelongsTo { return $this->belongsTo(GuiaRemision::class); }
    public function comprobanteElectronico(): BelongsTo { return $this->belongsTo(ComprobanteElectronico::class); }
    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function compra(): BelongsTo { return $this->belongsTo(Compra::class); }
}
