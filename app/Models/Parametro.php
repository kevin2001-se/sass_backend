<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Parametro extends Model
{
    use HasFactory;

    public const TIPO_BOOLEAN = 'boolean';
    public const TIPO_STRING = 'string';
    public const TIPO_INTEGER = 'integer';
    public const TIPO_DECIMAL = 'decimal';
    public const TIPO_JSON = 'json';

    public const GRUPOS = ['ventas', 'pos', 'inventario', 'compras', 'sunat', 'sistema'];
    public const TIPOS = [self::TIPO_BOOLEAN, self::TIPO_STRING, self::TIPO_INTEGER, self::TIPO_DECIMAL, self::TIPO_JSON];

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'clave',
        'valor',
        'tipo',
        'grupo',
        'descripcion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
}