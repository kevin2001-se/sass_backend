<?php

return [
    'cpe' => [
        'beta' => env('SUNAT_CPE_BETA_URL', 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService'),
        'produccion' => env('SUNAT_CPE_PRODUCCION_URL', 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService'),
    ],
    'gre' => [
        'beta' => [
            'token_url' => env('SUNAT_GRE_BETA_TOKEN_URL', 'https://gre-test.nubefact.com/v1'),
            'api_url' => env('SUNAT_GRE_BETA_API_URL', 'https://gre-test.nubefact.com/v1'),
            'scope' => env('SUNAT_GRE_BETA_SCOPE', 'https://api-cpe.sunat.gob.pe'),
        ],
        'produccion' => [
            'token_url' => env('SUNAT_GRE_PRODUCCION_TOKEN_URL', 'https://api-seguridad.sunat.gob.pe/v1'),
            'api_url' => env('SUNAT_GRE_PRODUCCION_API_URL', 'https://api-cpe.sunat.gob.pe/v1'),
            'scope' => env('SUNAT_GRE_PRODUCCION_SCOPE', 'https://api-cpe.sunat.gob.pe'),
        ],
    ],
];
