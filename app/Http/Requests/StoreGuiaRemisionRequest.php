<?php

namespace App\Http\Requests;

use App\Models\Distrito;
use App\Models\GuiaRemision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGuiaRemisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $modalidad = strtoupper(trim((string) $this->input('modalidad_transporte')));
        $modalidad = match ($modalidad) {
            'PUBLICO' => '01',
            'PRIVADO' => '02',
            default => $modalidad,
        };

        $puntoPartidaUbigeo = $this->ubigeoDesdeDistrito('punto_partida') ?: trim((string) $this->input('punto_partida_ubigeo'));
        $puntoLlegadaUbigeo = $this->ubigeoDesdeDistrito('punto_llegada') ?: trim((string) $this->input('punto_llegada_ubigeo'));

        $detalles = collect($this->input('detalles', []))->map(function ($detalle) {
            $detalle['descripcion'] = $this->normalizeText($detalle['descripcion'] ?? null);
            $detalle['unidad_medida'] = strtoupper(trim((string) ($detalle['unidad_medida'] ?? '')));

            return $detalle;
        })->all();

        $this->merge([
            'fecha_emision' => $this->input('fecha_emision', now()->toDateString()),
            'unidad_peso' => strtoupper(trim((string) $this->input('unidad_peso', 'KGM'))),
            'modalidad_transporte' => $modalidad,
            'motivo_traslado_codigo' => trim((string) $this->input('motivo_traslado_codigo')),
            'motivo_traslado_descripcion' => $this->normalizeText($this->input('motivo_traslado_descripcion')),
            'destinatario_tipo_documento' => strtoupper(trim((string) $this->input('destinatario_tipo_documento'))),
            'destinatario_numero_documento' => trim((string) $this->input('destinatario_numero_documento')),
            'destinatario_nombre' => $this->normalizeText($this->input('destinatario_nombre')),
            'punto_partida_ubigeo' => $puntoPartidaUbigeo,
            'punto_partida_direccion' => $this->normalizeText($this->input('punto_partida_direccion')),
            'punto_llegada_ubigeo' => $puntoLlegadaUbigeo,
            'punto_llegada_direccion' => $this->normalizeText($this->input('punto_llegada_direccion')),
            'conductor_tipo_documento' => strtoupper(trim((string) $this->input('conductor_tipo_documento'))),
            'conductor_numero_documento' => trim((string) $this->input('conductor_numero_documento')),
            'conductor_nombre' => $this->normalizeText($this->input('conductor_nombre')),
            'conductor_licencia' => strtoupper(trim((string) $this->input('conductor_licencia'))),
            'vehiculo_placa' => strtoupper(str_replace(' ', '', trim((string) $this->input('vehiculo_placa')))),
            'transportista_ruc' => trim((string) $this->input('transportista_ruc')),
            'transportista_razon_social' => $this->normalizeText($this->input('transportista_razon_social')),
            'estado' => strtoupper(trim((string) $this->input('estado', GuiaRemision::BORRADOR))),
            'detalles' => $detalles,
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->attributes->get('tenant')?->id;
        $empresaId = $this->attributes->get('empresa')?->id;
        $esRegistrada = $this->input('estado') === GuiaRemision::REGISTRADA;

        return [
            'fecha_emision' => ['required', 'date'],
            'fecha_traslado' => array_values(array_filter([
                'required',
                'date',
                'after_or_equal:fecha_emision',
                $esRegistrada ? 'after_or_equal:today' : null,
            ])),
            'motivo_traslado_codigo' => ['required', 'exists:motivos_traslado,codigo'],
            'motivo_traslado_descripcion' => [Rule::requiredIf($this->input('motivo_traslado_codigo') === '13'), 'nullable', 'string', 'max:255'],
            'modalidad_transporte' => ['required', 'exists:modalidades_transporte,codigo'],
            'cliente_id' => [
                'nullable',
                Rule::exists('clientes', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('empresa_id', $empresaId),
            ],
            'destinatario_tipo_documento' => ['required', Rule::in(['DNI', 'RUC', 'CE', 'PASAPORTE', 'SIN_DOCUMENTO'])],
            'destinatario_numero_documento' => ['required', 'string', 'max:20'],
            'destinatario_nombre' => ['required', 'string', 'max:255'],
            'punto_partida_departamento_id' => ['nullable', 'integer', 'exists:departamentos,id'],
            'punto_partida_provincia_id' => ['nullable', 'integer', 'exists:provincias,id'],
            'punto_partida_distrito_id' => ['nullable', 'integer', 'exists:distritos,id'],
            'punto_partida_ubigeo' => ['required', 'string', 'size:6'],
            'punto_partida_direccion' => ['required', 'string', 'max:255'],
            'punto_llegada_departamento_id' => ['nullable', 'integer', 'exists:departamentos,id'],
            'punto_llegada_provincia_id' => ['nullable', 'integer', 'exists:provincias,id'],
            'punto_llegada_distrito_id' => ['nullable', 'integer', 'exists:distritos,id'],
            'punto_llegada_ubigeo' => ['required', 'string', 'size:6'],
            'punto_llegada_direccion' => ['required', 'string', 'max:255'],
            'conductor_tipo_documento' => [Rule::requiredIf($this->input('modalidad_transporte') === '02'), 'nullable', Rule::in(['DNI', 'CE'])],
            'conductor_numero_documento' => [Rule::requiredIf($this->input('modalidad_transporte') === '02'), 'nullable', 'string', 'max:20'],
            'conductor_nombre' => [Rule::requiredIf($this->input('modalidad_transporte') === '02'), 'nullable', 'string', 'max:255'],
            'conductor_licencia' => [Rule::requiredIf($this->input('modalidad_transporte') === '02'), 'nullable', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/'],
            'vehiculo_placa' => [Rule::requiredIf($this->input('modalidad_transporte') === '02'), 'nullable', 'string', 'max:10'],
            'transportista_ruc' => [Rule::requiredIf($this->input('modalidad_transporte') === '01'), 'nullable', 'digits:11'],
            'transportista_razon_social' => [Rule::requiredIf($this->input('modalidad_transporte') === '01'), 'nullable', 'string', 'max:255'],
            'peso_total' => ['required', 'numeric', 'gt:0'],
            'unidad_peso' => ['required', 'exists:unidades_medida_sunat,codigo'],
            'numero_bultos' => ['nullable', 'integer', 'min:1'],
            'observacion' => ['nullable', 'string'],
            'estado' => ['nullable', Rule::in([GuiaRemision::BORRADOR, GuiaRemision::REGISTRADA])],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => [
                'nullable',
                Rule::exists('productos', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('empresa_id', $empresaId),
            ],
            'detalles.*.descripcion' => ['required', 'string', 'max:500'],
            'detalles.*.unidad_medida' => ['required', 'exists:unidades_medida_sunat,codigo'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.peso' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validarDocumentoDestinatario($validator);
            $this->validarDocumentoConductor($validator);
            $this->validarLicenciaConductor($validator);
            $this->validarJerarquiaUbigeo($validator, 'punto_partida');
            $this->validarJerarquiaUbigeo($validator, 'punto_llegada');
            $this->validarPuntosDiferentes($validator);
        });
    }


    protected function ubigeoDesdeDistrito(string $prefix): ?string
    {
        $distritoId = $this->input("{$prefix}_distrito_id");

        if (! $distritoId) {
            return null;
        }

        return Distrito::where('id', $distritoId)->value('ubigeo');
    }

    protected function validarJerarquiaUbigeo(Validator $validator, string $prefix): void
    {
        $departamentoId = $this->input("{$prefix}_departamento_id");
        $provinciaId = $this->input("{$prefix}_provincia_id");
        $distritoId = $this->input("{$prefix}_distrito_id");

        if ($provinciaId && $departamentoId) {
            $provinciaValida = \App\Models\Provincia::where('id', $provinciaId)
                ->where('departamento_id', $departamentoId)
                ->exists();

            if (! $provinciaValida) {
                $validator->errors()->add("{$prefix}_provincia_id", 'La provincia no pertenece al departamento seleccionado.');
            }
        }

        if ($distritoId && $provinciaId) {
            $distritoValido = Distrito::where('id', $distritoId)
                ->where('provincia_id', $provinciaId)
                ->exists();

            if (! $distritoValido) {
                $validator->errors()->add("{$prefix}_distrito_id", 'El distrito no pertenece a la provincia seleccionada.');
            }
        }
    }
    protected function validarDocumentoDestinatario(Validator $validator): void
    {
        $tipo = $this->input('destinatario_tipo_documento');
        $numero = (string) $this->input('destinatario_numero_documento');

        if ($tipo === 'DNI' && ! preg_match('/^\d{8}$/', $numero)) {
            $validator->errors()->add('destinatario_numero_documento', 'El DNI del destinatario debe tener 8 digitos.');
        }

        if ($tipo === 'RUC' && ! preg_match('/^\d{11}$/', $numero)) {
            $validator->errors()->add('destinatario_numero_documento', 'El RUC del destinatario debe tener 11 digitos.');
        }

        if ($tipo === 'CE' && strlen($numero) < 6) {
            $validator->errors()->add('destinatario_numero_documento', 'El CE del destinatario debe tener minimo 6 caracteres.');
        }

        if ($tipo === 'SIN_DOCUMENTO' && $numero !== '00000000') {
            $validator->errors()->add('destinatario_numero_documento', 'Para SIN_DOCUMENTO use 00000000.');
        }
    }

    protected function validarDocumentoConductor(Validator $validator): void
    {
        if ($this->input('modalidad_transporte') !== '02') {
            return;
        }

        $tipo = $this->input('conductor_tipo_documento');
        $numero = (string) $this->input('conductor_numero_documento');

        if ($tipo === 'DNI' && ! preg_match('/^\d{8}$/', $numero)) {
            $validator->errors()->add('conductor_numero_documento', 'El DNI del conductor debe tener 8 digitos.');
        }

        if ($tipo === 'CE' && strlen($numero) < 6) {
            $validator->errors()->add('conductor_numero_documento', 'El CE del conductor debe tener minimo 6 caracteres.');
        }
    }

    protected function validarLicenciaConductor(Validator $validator): void
    {
        if ($this->input('modalidad_transporte') !== '02') {
            return;
        }

        $licencia = (string) $this->input('conductor_licencia');
        $normalizada = preg_replace('/[^A-Z0-9]/', '', strtoupper($licencia)) ?: '';

        if ($licencia !== '' && strlen($normalizada) < 5) {
            $validator->errors()->add('conductor_licencia', 'La licencia del conductor debe tener al menos 5 caracteres alfanumericos. Ejemplo: Q12345678.');
        }

        if ($licencia !== '' && preg_match('/[^A-Z0-9-]/', $licencia)) {
            $validator->errors()->add('conductor_licencia', 'La licencia del conductor solo debe contener letras, numeros o guion.');
        }
    }
    protected function validarPuntosDiferentes(Validator $validator): void
    {
        $mismoUbigeo = $this->input('punto_partida_ubigeo') === $this->input('punto_llegada_ubigeo');
        $mismaDireccion = $this->normalizarDireccion($this->input('punto_partida_direccion')) === $this->normalizarDireccion($this->input('punto_llegada_direccion'));

        if ($mismoUbigeo && $mismaDireccion) {
            $validator->errors()->add('punto_llegada_direccion', 'El punto de partida y llegada no pueden ser exactamente iguales.');
        }
    }

    protected function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', (string) $value));

        return $value === '' ? null : strtoupper($value);
    }

    protected function normalizarDireccion(mixed $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $value)));
    }
}
