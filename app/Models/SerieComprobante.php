<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerieComprobante extends Model
{
    use HasFactory;

    protected $table = 'series_comprobantes';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'tipo_comprobante',
        'serie',
        'correlativo_actual',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'correlativo_actual' => 'integer',
            'estado' => 'boolean',
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
}
