<?php

namespace App\Http\Requests\NotasDebito;

use Illuminate\Foundation\Http\FormRequest;

class AnularNotaDebitoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'max:500']];
    }
}