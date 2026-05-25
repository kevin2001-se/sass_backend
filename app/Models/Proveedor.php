<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = ['tenant_id', 'empresa_id', 'tipo_documento', 'numero_documento', 'razon_social', 'nombre_comercial', 'direccion', 'telefono', 'email', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function compras(): HasMany { return $this->hasMany(Compra::class); }
    public function cuentasPorPagar(): HasMany { return $this->hasMany(CuentaPorPagar::class); }
}
