<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoConfiguracion extends Model
{
    use HasFactory;

    protected $table = 'producto_configuraciones';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'autogenerar_codigo_interno',
        'prefijo_codigo_interno',
        'ultimo_correlativo_codigo_interno',
        'autogenerar_codigo_barra',
        'prefijo_codigo_barra',
        'ultimo_correlativo_codigo_barra',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'autogenerar_codigo_interno' => 'boolean',
            'ultimo_correlativo_codigo_interno' => 'integer',
            'autogenerar_codigo_barra' => 'boolean',
            'ultimo_correlativo_codigo_barra' => 'integer',
            'estado' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
}
