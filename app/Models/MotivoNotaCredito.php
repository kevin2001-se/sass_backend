<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotivoNotaCredito extends Model
{
    use HasFactory;

    protected $table = 'motivos_nota_credito';

    protected $fillable = [
        'codigo',
        'descripcion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }
}
