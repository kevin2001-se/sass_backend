<?php

namespace App\Services\Sunat;

use App\Models\SunatConfiguracion;
use Greenter\Api;
use Greenter\See;
use Greenter\Sunat\GRE\Api\AuthApi;
use Greenter\Sunat\GRE\Api\CpeApi;
use Greenter\Sunat\GRE\ApiException;
use Greenter\Sunat\GRE\Configuration;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GreSunatClientFactory
{
    public function __construct(private readonly SunatConfiguracionService $configuracionService)
    {
    }

    public function validarConfiguracion(SunatConfiguracion $configuracion): void
    {
        $this->configuracionService->validarGre($configuracion);

        if (! class_exists(AuthApi::class) || ! class_exists(CpeApi::class)) {
            throw new RuntimeException('Greenter GRE API no esta instalado. Ejecute composer require greenter/gre-api.');
        }
    }

    public function makeApi(SunatConfiguracion $configuracion): Api
    {
        $this->validarConfiguracion($configuracion);

        if (! class_exists(Api::class)) {
            throw new RuntimeException('Greenter Api no esta instalado. Ejecute composer require greenter/greenter.');
        }

        $greCredenciales = $this->configuracionService->obtenerCredencialesGreDesencriptadas($configuracion);
        $credenciales = $this->configuracionService->obtenerCredencialesDesencriptadas($configuracion);
        $certificadoContenido = $this->certificadoContenido($configuracion);
        $certificadoPem = $this->normalizarCertificadoPem($certificadoContenido, $credenciales['certificado_password']);

        $api = new Api([
            'auth' => $greCredenciales['token_url'],
            'cpe' => $greCredenciales['api_url'],
        ]);

        $tokenAttempt = $this->tokenAttemptForApi($configuracion, $greCredenciales);

        $api->setApiCredentials($greCredenciales['client_id'], $greCredenciales['client_secret']);
        $api->setClaveSOL($tokenAttempt['ruc'], $tokenAttempt['usuario_sol'], $tokenAttempt['clave_sol']);
        $api->setCertificate($certificadoPem);
        $api->setBuilderOptions([
            'cache' => false,
            'strict_variables' => true,
        ]);

        return $api;
    }
    public function probarAutorizacion(SunatConfiguracion $configuracion): void
    {
        try {
            $this->obtenerAccessToken($configuracion);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->lanzarErrorAutorizacion($e->getMessage());
        }
    }
    public function makeSigner(SunatConfiguracion $configuracion): See
    {
        if (! class_exists(See::class)) {
            throw new RuntimeException('Greenter no esta instalado. Ejecute composer require greenter/greenter.');
        }

        $credenciales = $this->configuracionService->obtenerCredencialesDesencriptadas($configuracion);
        $certificadoContenido = $this->certificadoContenido($configuracion);
        $certificadoPem = $this->normalizarCertificadoPem($certificadoContenido, $credenciales['certificado_password']);

        $see = new See();
        $see->setCertificate($certificadoPem);
        $see->setClaveSOL($credenciales['ruc'], $credenciales['usuario_sol'], $credenciales['clave_sol']);

        return $see;
    }

    public function makeCpeApi(SunatConfiguracion $configuracion): CpeApi
    {
        $this->validarConfiguracion($configuracion);
        $token = $this->obtenerAccessToken($configuracion);
        $greCredenciales = $this->configuracionService->obtenerCredencialesGreDesencriptadas($configuracion);

        $config = Configuration::getDefaultConfiguration()
            ->setAccessToken($token)
            ->setHost($greCredenciales['api_url']);

        return new CpeApi(new Client(), $config);
    }

    public function obtenerAccessToken(SunatConfiguracion $configuracion): string
    {
        $this->validarConfiguracion($configuracion);
        $credenciales = $this->configuracionService->obtenerCredencialesGreDesencriptadas($configuracion);

        $attempts = [$this->buildTokenAttempt($configuracion, $credenciales, false)];

        if ($this->esGreBetaNubefact($credenciales)) {
            $attempts[] = $this->buildTokenAttempt($configuracion, $credenciales, true);
        }

        $lastError = null;

        foreach ($attempts as $attempt) {
            try {
                $token = $this->requestAccessToken($attempt, $credenciales);

                if (! empty($token)) {
                    return $token;
                }

                $lastError = 'SUNAT GRE no devolvio access token.';
            } catch (RuntimeException $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new RuntimeException($lastError ?: 'SUNAT GRE no devolvio access token.');
    }

    protected function requestAccessToken(array $attempt, array $credenciales): ?string
    {
        $url = rtrim((string) $credenciales['token_url'], '/').'/clientessol/'.$credenciales['client_id'].'/oauth2/token/';

        try {
            $response = (new Client())->post($url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'grant_type' => 'password',
                    'scope' => $attempt['scope'],
                    'client_id' => $credenciales['client_id'],
                    'client_secret' => $credenciales['client_secret'],
                    'username' => $attempt['username'],
                    'password' => $attempt['password'],
                ],
                'http_errors' => true,
                'timeout' => 30,
            ]);
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : $e->getCode();
            throw new RuntimeException($this->formatearHttpErrorAutorizacion((int) $status, $body, $credenciales, $attempt['username'], $url, $e->getMessage(), $attempt['scope'], $attempt['label']));
        } catch (\Throwable $e) {
            throw new RuntimeException('Error conectando con token GRE: '.$e->getMessage());
        }

        $status = $response->getStatusCode();
        $rawBody = (string) $response->getBody();
        $decoded = json_decode($rawBody, true);
        $accessToken = is_array($decoded) ? ($decoded['access_token'] ?? null) : null;

        if (! $accessToken) {
            $bodyText = $this->sanitizeErrorText($rawBody);
            Log::warning('SUNAT GRE auth token empty', [
                'attempt' => $attempt['label'],
                'http_status' => $status,
                'token_url' => $credenciales['token_url'],
                'api_url' => $credenciales['api_url'],
                'scope_configurado' => $credenciales['scope'],
                'scope_enviado' => $attempt['scope'],
                'username' => substr($attempt['username'], 0, 11).'***',
                'client_id_prefix' => substr((string) $credenciales['client_id'], 0, 8),
                'raw_body' => $bodyText,
            ]);

            return null;
        }

        Log::info('SUNAT GRE auth token ok', [
            'attempt' => $attempt['label'],
            'token_url' => $credenciales['token_url'],
            'api_url' => $credenciales['api_url'],
            'scope_enviado' => $attempt['scope'],
            'username' => substr($attempt['username'], 0, 11).'***',
            'client_id_prefix' => substr((string) $credenciales['client_id'], 0, 8),
        ]);

        return $accessToken;
    }

    protected function tokenAttemptForApi(SunatConfiguracion $configuracion, array $credenciales): array
    {
        if ($this->esGreBetaNubefact($credenciales)) {
            return [
                'ruc' => '20161515648',
                'usuario_sol' => 'MODDATOS',
                'clave_sol' => 'MODDATOS',
            ];
        }

        return [
            'ruc' => $configuracion->ruc,
            'usuario_sol' => $credenciales['usuario_sol'],
            'clave_sol' => $credenciales['clave_sol'],
        ];
    }
    protected function buildTokenAttempt(SunatConfiguracion $configuracion, array $credenciales, bool $betaFallback): array
    {
        if ($betaFallback) {
            return [
                'label' => 'beta_nubefact_20161515648_moddatos',
                'username' => '20161515648MODDATOS',
                'password' => 'MODDATOS',
                'scope' => $this->scopeOAuthGre($credenciales),
            ];
        }

        return [
            'label' => 'configuracion_empresa',
            'username' => $configuracion->ruc.$credenciales['usuario_sol'],
            'password' => $credenciales['clave_sol'],
            'scope' => $this->scopeOAuthGre($credenciales),
        ];
    }

    protected function esGreBetaNubefact(array $credenciales): bool
    {
        return str_contains((string) $credenciales['token_url'], 'gre-test.nubefact.com');
    }
    protected function formatearHttpErrorAutorizacion(int $status, string $body, array $credenciales, string $username, string $url, string $message, string $scope, string $attempt = "configuracion_empresa"): string
    {
        $bodyText = $this->sanitizeErrorText($body);
        $message = $this->sanitizeErrorText($message);
        $safeUrl = rtrim((string) $credenciales['token_url'], '/').'/clientessol/'.substr((string) $credenciales['client_id'], 0, 8).'.../oauth2/token/';

        Log::warning('SUNAT GRE auth http error', [
            'http_status' => $status,
            'token_url' => $credenciales['token_url'],
            'api_url' => $credenciales['api_url'],
            'scope_configurado' => $credenciales['scope'],
            'scope_enviado' => $scope,
            'username' => substr($username, 0, 11).'***',
            'client_id_prefix' => substr((string) $credenciales['client_id'], 0, 8),
            'response_body' => $bodyText,
            'message' => $message,
        ]);

        return 'Error exacto autorizaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n GRE. HTTP '.$status.'. Endpoint: '.$safeUrl.'. Respuesta: '.($bodyText !== '' ? $bodyText : 'sin cuerpo de respuesta').'. Mensaje: '.$message;
    }
    protected function scopeOAuthGre(array $credenciales): string
    {
        $scope = trim((string) ($credenciales['scope'] ?? ''));

        if ($scope === '' || str_contains($scope, 'gre-test.nubefact.com')) {
            return 'https://api-cpe.sunat.gob.pe';
        }

        return $scope;
    }
    protected function formatearApiExceptionAutorizacion(ApiException $e, array $credenciales, string $username): string
    {
        $body = $e->getResponseBody();
        $bodyText = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $bodyText = $this->sanitizeErrorText($bodyText ?: '');
        $message = $this->sanitizeErrorText($e->getMessage());
        $endpoint = rtrim((string) $credenciales['token_url'], '/').'/clientessol/'.substr((string) $credenciales['client_id'], 0, 8).'.../oauth2/token/';

        Log::warning('SUNAT GRE auth error', [
            'http_status' => $e->getCode(),
            'token_url' => $credenciales['token_url'],
            'api_url' => $credenciales['api_url'],
            'scope' => $this->scopeOAuthGre($credenciales),
            'username' => substr($username, 0, 11).'***',
            'client_id_prefix' => substr((string) $credenciales['client_id'], 0, 8),
            'response_body' => $bodyText,
            'message' => $message,
        ]);

        return trim(sprintf(
            'Error exacto autorizaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n GRE. HTTP %s. Endpoint: %s. Respuesta: %s. Mensaje: %s',
            $e->getCode() ?: 'N/D',
            $endpoint,
            $bodyText !== '' ? $bodyText : 'sin cuerpo de respuesta',
            $message
        ));
    }

    protected function sanitizeErrorText(?string $text): string
    {
        $text = (string) $text;
        $text = preg_replace('/(client_secret=)[^&\s]+/i', '$1***', $text) ?? $text;
        $text = preg_replace('/(password=)[^&\s]+/i', '$1***', $text) ?? $text;
        $text = preg_replace('/("client_secret"\s*:\s*")[^"]+/i', '$1***', $text) ?? $text;
        $text = preg_replace('/("password"\s*:\s*")[^"]+/i', '$1***', $text) ?? $text;

        return trim($text);
    }
    protected function lanzarErrorAutorizacion(?string $message): void
    {
        $message = $message ?: 'No se pudo autorizar con SUNAT GRE.';

        if (str_contains(strtolower($message), 'cliente no autorizado') || str_contains(strtolower($message), 'unauthorized')) {
            throw new RuntimeException('Cliente No autorizado. Este error ocurre antes de firmar la guia, por lo que no lo causa el certificado. Revise GRE Client ID, GRE Client Secret, usuario SOL GRE, clave SOL GRE, ambiente y endpoints GRE. En BETA debe usar credenciales beta; en PRODUCCION debe usar credenciales API SUNAT del RUC emisor.');
        }

        throw new RuntimeException($message);
    }
    protected function certificadoContenido(SunatConfiguracion $configuracion): string
    {
        if (! $configuracion->certificado_path) {
            throw new RuntimeException('El certificado digital SUNAT es obligatorio para firmar la guia.');
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
}