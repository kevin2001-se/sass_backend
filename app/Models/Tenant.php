<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'active',
    ];

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class);
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class);
    }

    public function cajaMovimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class);
    }

    public function sunatConfiguraciones(): HasMany
    {
        return $this->hasMany(SunatConfiguracion::class);
    }

    public function comprobantesElectronicos(): HasMany
    {
        return $this->hasMany(ComprobanteElectronico::class);
    }

    public function notasElectronicas(): HasMany
    {
        return $this->hasMany(NotaElectronica::class);
    }

    public function resumenesDiarios(): HasMany
    {
        return $this->hasMany(ResumenDiario::class);
    }

    public function comunicacionesBaja(): HasMany
    {
        return $this->hasMany(ComunicacionBaja::class);
    }

    public function guiasRemision(): HasMany
    {
        return $this->hasMany(GuiaRemision::class);
    }
}
