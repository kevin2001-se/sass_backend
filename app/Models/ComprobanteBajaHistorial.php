<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComprobanteBajaHistorial extends Model
{
    use HasFactory;

    protected $table = 'comprobante_bajas_historial';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'comprobante_id',
        'estado_anterior',
        'estado_nuevo',
        'motivo',
        'usuario_id',
    ];

    public function comprobante(): BelongsTo { return $this->belongsTo(ComprobanteElectronico::class, 'comprobante_id'); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'usuario_id'); }
}