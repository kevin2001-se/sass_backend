<?php

namespace App\Http\Requests\NotasCredito;

use Illuminate\Foundation\Http\FormRequest;

class AnularNotaCreditoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }
}
