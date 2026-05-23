<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tienda extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'nombre',
        'codigo',
        'direccion',
        'estado',
    ];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tienda_user')
            ->withPivot(['tenant_id', 'empresa_id', 'estado'])
            ->withTimestamps();
    }


    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class);
    }

    public function seriesComprobantes(): HasMany
    {
        return $this->hasMany(SerieComprobante::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class);
    }

    public function cajaMovimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class);
    }

    public function compras(): HasMany { return $this->hasMany(Compra::class); }
    public function cuentasPorPagar(): HasMany { return $this->hasMany(CuentaPorPagar::class); }

    public function cuentasPorCobrar(): HasMany { return $this->hasMany(CuentaPorCobrar::class); }

    public function notasElectronicas(): HasMany { return $this->hasMany(NotaElectronica::class); }

    public function resumenesDiarios(): HasMany { return $this->hasMany(ResumenDiario::class); }

    public function comunicacionesBaja(): HasMany { return $this->hasMany(ComunicacionBaja::class); }

    public function guiasRemision(): HasMany { return $this->hasMany(GuiaRemision::class); }
}
