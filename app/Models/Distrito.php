<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Distrito extends Model
{
    use HasFactory;

    protected $fillable = ['provincia_id', 'codigo', 'nombre', 'ubigeo', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }
}
