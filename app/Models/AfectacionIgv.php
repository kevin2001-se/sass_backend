<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AfectacionIgv extends Model
{
    use HasFactory;

    protected $table = 'afectaciones_igv';

    protected $fillable = [
        'codigo',
        'descripcion',
        'abreviatura',
        'aplica_igv',
        'es_gratuito',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'aplica_igv' => 'boolean',
            'es_gratuito' => 'boolean',
            'estado' => 'boolean',
        ];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }
}
