<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReenviarComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'observacion' => ['nullable', 'string'],
        ];
    }
}
