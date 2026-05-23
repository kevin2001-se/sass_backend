<?php

namespace App\Services\Sunat;

use App\Models\SunatConfiguracion;
use Greenter\See;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SunatClientFactory
{
    public function __construct(private readonly SunatConfiguracionService $configuracionService)
    {
    }

    public function make(SunatConfiguracion $configuracion): See
    {
        if (! class_exists(See::class)) {
            throw new RuntimeException('Greenter no esta instalado. Ejecute composer require greenter/greenter.');
        }

        $credenciales = $this->configuracionService->obtenerCredencialesDesencriptadas($configuracion);
        $certificadoContenido = $this->certificadoContenido($configuracion);
        $certificadoPem = $this->normalizarCertificadoPem($certificadoContenido, $credenciales['certificado_password']);

        $see = new See();
        $see->setCertificate($certificadoPem);
        $see->setService($this->endpointPorAmbiente($configuracion->ambiente));
        $see->setClaveSOL($credenciales['ruc'], $credenciales['usuario_sol'], $credenciales['clave_sol']);

        return $see;
    }

    protected function certificadoContenido(SunatConfiguracion $configuracion): string
    {
        if (! $configuracion->certificado_path) {
            throw new RuntimeException('El certificado digital SUNAT es obligatorio para firmar y enviar comprobantes.');
        }

        if (! Storage::disk('local')->exists($configuracion->certificado_path)) {
            throw new RuntimeException('El certificado digital SUNAT no existe en storage privado.');
        }

        return Storage::disk('local')->get($configuracion->certificado_path);
    }

    protected function normalizarCertificadoPem(string $contenido, ?string $password): string
    {
        if (str_contains($contenido, '-----BEGIN CERTIFICATE-----')) {
            if (! str_contains($contenido, '-----BEGIN') || ! str_contains($contenido, 'PRIVATE KEY-----')) {
                throw new RuntimeException('El certificado PEM debe incluir certificado y llave privada.');
            }

            return $contenido;
        }

        $certs = [];

        if (! openssl_pkcs12_read($contenido, $certs, $password ?? '')) {
            throw new RuntimeException('No se pudo leer el certificado PFX/P12. Verifique la contrasena del certificado o suba un PEM valido.');
        }

        return ($certs['cert'] ?? '').PHP_EOL.($certs['pkey'] ?? '');
    }

    protected function endpointPorAmbiente(string $ambiente): string
    {
        return $ambiente === 'PRODUCCION'
            ? (string) config('sunat.cpe.produccion')
            : (string) config('sunat.cpe.beta');
    }
}