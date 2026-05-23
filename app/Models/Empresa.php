<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'nombre',
        'ruc',
        'direccion',
        'active',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tiendas(): HasMany
    {
        return $this->hasMany(Tienda::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class);
    }

    public function marcas(): HasMany
    {
        return $this->hasMany(Marca::class);
    }

    public function laboratorios(): HasMany
    {
        return $this->hasMany(Laboratorio::class);
    }

    public function principiosActivos(): HasMany
    {
        return $this->hasMany(PrincipioActivo::class);
    }

    public function accionesTerapeuticas(): HasMany
    {
        return $this->hasMany(AccionTerapeutica::class);
    }

    public function unidadesMedida(): HasMany
    {
        return $this->hasMany(UnidadMedida::class);
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

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
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

    public function proveedores(): HasMany { return $this->hasMany(Proveedor::class); }
    public function compras(): HasMany { return $this->hasMany(Compra::class); }
    public function cuentasPorPagar(): HasMany { return $this->hasMany(CuentaPorPagar::class); }

    public function cuentasPorCobrar(): HasMany { return $this->hasMany(CuentaPorCobrar::class); }

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
