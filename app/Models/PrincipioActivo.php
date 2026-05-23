<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PrincipioActivo extends Model
{
    use HasFactory;

    protected $table = 'principios_activos';

    protected $fillable = ['tenant_id', 'empresa_id', 'nombre', 'descripcion', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_principio_activo')
            ->withPivot(['tenant_id', 'empresa_id'])
            ->withTimestamps();
    }
}
