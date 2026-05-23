<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class SunatConfiguracion extends Model
{
    use HasFactory;

    protected $table = 'sunat_configuraciones';

    protected $fillable = [
        'tenant_id',
        'empresa_id',
        'ruc',
        'razon_social',
        'nombre_comercial',
        'direccion_fiscal',
        'ubigeo',
        'departamento',
        'provincia',
        'distrito',
        'usuario_sol',
        'clave_sol',
        'certificado_path',
        'certificado_password',
        'ambiente',
        'modo_envio',
        'gre_client_id',
        'gre_client_secret',
        'gre_usuario_sol',
        'gre_clave_sol',
        'gre_scope',
        'gre_token_url',
        'gre_api_url',
        'gre_modo_envio',
        'estado',
    ];

    protected $hidden = [
        'clave_sol',
        'certificado_password',
        'gre_client_secret',
        'gre_clave_sol',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'gre_modo_envio' => 'boolean',
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

    public function tieneCertificado(): bool
    {
        return filled($this->certificado_path);
    }

    public function tieneGreCredenciales(): bool
    {
        return filled($this->gre_client_id)
            && filled($this->gre_client_secret)
            && filled($this->gre_usuario_sol)
            && filled($this->gre_clave_sol);
    }

    public function greHabilitado(): bool
    {
        return (bool) $this->gre_modo_envio && $this->tieneGreCredenciales();
    }

    public function claveSolDesencriptada(): string
    {
        return Crypt::decryptString($this->clave_sol);
    }

    public function certificadoPasswordDesencriptada(): ?string
    {
        return $this->certificado_password ? Crypt::decryptString($this->certificado_password) : null;
    }

    public function greClientSecretDesencriptado(): ?string
    {
        return $this->gre_client_secret ? Crypt::decryptString($this->gre_client_secret) : null;
    }

    public function greClaveSolDesencriptada(): ?string
    {
        return $this->gre_clave_sol ? Crypt::decryptString($this->gre_clave_sol) : null;
    }
}