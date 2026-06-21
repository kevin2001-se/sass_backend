<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoProveedor extends Model
{
    use HasFactory;

    protected $table = 'pagos_proveedor';

    public const REGISTRADO = 'REGISTRADO';
    public const ANULADO = 'ANULADO';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'tienda_id',
        'cuenta_por_pagar_id',
        'proveedor_id',
        'caja_id',
        'metodo_pago',
        'monto',
        'referencia',
        'fecha_pago',
        'observacion',
        'estado',
        'created_by',
        'anulado_by',
        'anulado_at',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'date',
            'anulado_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function tienda(): BelongsTo { return $this->belongsTo(Tienda::class); }
    public function cuentaPorPagar(): BelongsTo { return $this->belongsTo(CuentaPorPagar::class); }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function caja(): BelongsTo { return $this->belongsTo(Caja::class); }
    public function creadoPor(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function anuladoPor(): BelongsTo { return $this->belongsTo(User::class, 'anulado_by'); }
}