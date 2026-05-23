<?php

namespace App\Http\Requests;

use App\Models\GuiaRemision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGuiaDesdeVentaRequest extends FormRequest
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

        $this->merge([
            'fecha_emision' => $this->input('fecha_emision', now()->toDateString()),
            'unidad_peso' => strtoupper(trim((string) $this->input('unidad_peso', 'KGM'))),
            'modalidad_transporte' => $modalidad,
            'motivo_traslado_codigo' => trim((string) $this->input('motivo_traslado_codigo')),
            'motivo_traslado_descripcion' => $this->normalizeText($this->input('motivo_traslado_descripcion')),
            'punto_llegada_ubigeo' => trim((string) $this->input('punto_llegada_ubigeo')),
            'punto_llegada_direccion' => $this->normalizeText($this->input('punto_llegada_direccion')),
            'conductor_tipo_documento' => strtoupper(trim((string) $this->input('conductor_tipo_documento'))),
            'conductor_numero_documento' => trim((string) $this->input('conductor_numero_documento')),
            'conductor_nombre' => $this->normalizeText($this->input('conductor_nombre')),
            'conductor_licencia' => strtoupper(trim((string) $this->input('conductor_licencia'))),
            'vehiculo_placa' => strtoupper(str_replace(' ', '', trim((string) $this->input('vehiculo_placa')))),
            'transportista_ruc' => trim((string) $this->input('transportista_ruc')),
            'transportista_razon_social' => $this->normalizeText($this->input('transportista_razon_social')),
            'estado' => strtoupper(trim((string) $this->input('estado', GuiaRemision::BORRADOR))),
        ]);
    }

    public function rules(): array
    {
        $esRegistrada = $this->input('estado') === GuiaRemision::REGISTRADA;

        return [
            'fecha_emision' => ['nullable', 'date'],
            'fecha_traslado' => array_values(array_filter([
                'required',
                'date',
                'after_or_equal:fecha_emision',
                $esRegistrada ? 'after_or_equal:today' : null,
            ])),
            'motivo_traslado_codigo' => ['required', 'exists:motivos_traslado,codigo'],
            'motivo_traslado_descripcion' => [Rule::requiredIf($this->input('motivo_traslado_codigo') === '13'), 'nullable', 'string', 'max:255'],
            'modalidad_transporte' => ['required', 'exists:modalidades_transporte,codigo'],
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
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
        });
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
    protected function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', (string) $value));

        return $value === '' ? null : strtoupper($value);
    }
}