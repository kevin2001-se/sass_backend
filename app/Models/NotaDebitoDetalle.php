<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaDebitoDetalle extends Model
{
    use HasFactory;

    protected $table = 'nota_debito_detalles';

    protected $fillable = [
        'tenant_id', 'empresa_id', 'nota_debito_id', 'descripcion',
        'cantidad', 'precio_unitario', 'subtotal', 'igv', 'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function notaDebito(): BelongsTo { return $this->belongsTo(NotaDebito::class); }
}