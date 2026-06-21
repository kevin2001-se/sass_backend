<?php

use App\Services\ParametroService;

if (! function_exists('parametro')) {
    function parametro(string $clave, mixed $default = null): mixed
    {
        return app(ParametroService::class)->get($clave, $default);
    }
}