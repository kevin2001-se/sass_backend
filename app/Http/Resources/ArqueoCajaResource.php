<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ArqueoCajaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'monto_apertura' => $this['monto_apertura'],
            'ingresos_efectivo' => $this['ingresos_efectivo'],
            'ingresos_yape' => $this['ingresos_yape'],
            'ingresos_plin' => $this['ingresos_plin'],
            'ingresos_tarjeta' => $this['ingresos_tarjeta'],
            'ingresos_transferencia' => $this['ingresos_transferencia'],
            'total_ingresos' => $this['total_ingresos'],
            'total_egresos' => $this['total_egresos'],
            'saldo_sistema' => $this['saldo_sistema'],
            'monto_real' => $this['monto_real'],
            'diferencia' => $this['diferencia'],
        ];
    }
}
