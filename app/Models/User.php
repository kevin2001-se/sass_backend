<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_activa_id',
        'role_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function tiendaActiva(): BelongsTo
    {
        return $this->belongsTo(Tienda::class, 'tienda_activa_id');
    }

    public function activeTienda(): BelongsTo
    {
        return $this->tiendaActiva();
    }

    public function tiendas(): BelongsToMany
    {
        return $this->belongsToMany(Tienda::class, 'tienda_user')
            ->withPivot(['tenant_id', 'empresa_id', 'estado'])
            ->withTimestamps();
    }

    public function tiendasActivas(): BelongsToMany
    {
        return $this->tiendas()->wherePivot('estado', true)->where('tiendas.estado', true);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function cajasAperturadas(): HasMany
    {
        return $this->hasMany(Caja::class, 'user_apertura_id');
    }

    public function cajasCerradas(): HasMany
    {
        return $this->hasMany(Caja::class, 'user_cierre_id');
    }

    public function cajaMovimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class);
    }

    public function compras(): HasMany { return $this->hasMany(Compra::class); }
    public function cuentasPorPagarPagos(): HasMany { return $this->hasMany(CuentaPorPagarPago::class); }

    public function cuentasPorCobrarPagos(): HasMany { return $this->hasMany(CuentaPorCobrarPago::class); }
}
