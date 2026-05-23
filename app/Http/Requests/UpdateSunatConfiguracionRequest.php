<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSunatConfiguracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ruc' => ['sometimes', 'required', 'digits:11'],
            'razon_social' => ['sometimes', 'required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'direccion_fiscal' => ['sometimes', 'required', 'string', 'max:255'],
            'ubigeo' => ['sometimes', 'required', 'string', 'size:6'],
            'departamento' => ['sometimes', 'required', 'string', 'max:100'],
            'provincia' => ['sometimes', 'required', 'string', 'max:100'],
            'distrito' => ['sometimes', 'required', 'string', 'max:100'],
            'usuario_sol' => ['sometimes', 'required', 'string', 'max:100'],
            'clave_sol' => ['nullable', 'string', 'max:255'],
            'certificado' => ['nullable', 'file', 'max:10240'],
            'certificado_password' => ['nullable', 'string', 'max:255'],
            'ambiente' => ['sometimes', 'required', 'in:BETA,PRODUCCION'],
            'modo_envio' => ['sometimes', 'required', 'in:MANUAL,AUTOMATICO'],
            'gre_client_id' => ['nullable', 'string', 'max:255'],
            'gre_client_secret' => ['nullable', 'string', 'max:1000'],
            'gre_usuario_sol' => ['nullable', 'string', 'max:100'],
            'gre_clave_sol' => ['nullable', 'string', 'max:255'],
            'gre_scope' => ['nullable', 'string', 'max:255'],
            'gre_token_url' => ['nullable', 'url', 'max:255'],
            'gre_api_url' => ['nullable', 'url', 'max:255'],
            'gre_modo_envio' => ['sometimes', 'boolean'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $file = $this->file('certificado');

            if (! $file) {
                return;
            }

            $extension = strtolower($file->getClientOriginalExtension());

            if (! in_array($extension, ['pfx', 'p12', 'pem'], true)) {
                $validator->errors()->add('certificado', 'El certificado debe tener extension .pfx, .p12 o .pem.');
            }
        });
    }
}