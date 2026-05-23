<?php

namespace App\Http\Resources;

use App\Services\Sunat\SunatConfiguracionService;
use Illuminate\Http\Resources\Json\JsonResource;

class SunatConfiguracionResource extends JsonResource
{
    public function toArray($request): array
    {
        $service = app(SunatConfiguracionService::class);

        return [
            'id' => $this->id,
            'empresa_id' => $this->empresa_id,
            'ruc' => $this->ruc,
            'razon_social' => $this->razon_social,
            'nombre_comercial' => $this->nombre_comercial,
            'direccion_fiscal' => $this->direccion_fiscal,
            'ubigeo' => $this->ubigeo,
            'departamento' => $this->departamento,
            'provincia' => $this->provincia,
            'distrito' => $this->distrito,
            'usuario_sol' => $this->usuario_sol,
            'ambiente' => $this->ambiente,
            'modo_envio' => $this->modo_envio,
            'estado' => $this->estado,
            'tiene_certificado' => $this->tieneCertificado(),
            'gre_modo_envio' => (bool) $this->gre_modo_envio,
            'gre_habilitado' => $this->greHabilitado(),
            'tiene_gre_credenciales' => $this->tieneGreCredenciales(),
            'gre_client_id' => $this->gre_client_id,
            'gre_usuario_sol' => $this->gre_usuario_sol,
            'gre_scope' => $service->greScope($this->resource),
            'gre_token_url' => $service->greTokenUrl($this->resource),
            'gre_api_url' => $service->greApiUrl($this->resource),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}