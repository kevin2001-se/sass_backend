<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'categoria_id',
        'marca_id',
        'laboratorio_id',
        'principio_activo_id',
        'accion_terapeutica_id',
        'afectacion_igv_id',
        'codigo_interno',
        'nombre',
        'descripcion',
        'concentracion',
        'requiere_receta',
        'maneja_lote',
        'maneja_vencimiento',
        'afecto_igv',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'requiere_receta' => 'boolean',
            'maneja_lote' => 'boolean',
            'maneja_vencimiento' => 'boolean',
            'afecto_igv' => 'boolean',
            'estado' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function categoria(): BelongsTo { return $this->belongsTo(Categoria::class); }
    public function marca(): BelongsTo { return $this->belongsTo(Marca::class); }
    public function laboratorio(): BelongsTo { return $this->belongsTo(Laboratorio::class); }
    public function principioActivo(): BelongsTo { return $this->belongsTo(PrincipioActivo::class); }
    public function accionTerapeutica(): BelongsTo { return $this->belongsTo(AccionTerapeutica::class); }
    public function afectacionIgv(): BelongsTo { return $this->belongsTo(AfectacionIgv::class); }

    public function principiosActivos(): BelongsToMany
    {
        return $this->belongsToMany(PrincipioActivo::class, 'producto_principio_activo')
            ->withPivot(['tenant_id', 'empresa_id'])
            ->withTimestamps();
    }

    public function presentaciones(): HasMany { return $this->hasMany(ProductoPresentacion::class); }
    public function presentacionPrincipal(): HasOne { return $this->hasOne(ProductoPresentacion::class)->where('es_principal', true); }
    public function lotes(): HasMany { return $this->hasMany(Lote::class); }
    public function stocks(): HasMany { return $this->hasMany(Stock::class); }
    public function movimientosInventario(): HasMany { return $this->hasMany(InventarioMovimiento::class); }
    public function ventaDetalles(): HasMany { return $this->hasMany(VentaDetalle::class); }
    public function compraDetalles(): HasMany { return $this->hasMany(CompraDetalle::class); }
}
