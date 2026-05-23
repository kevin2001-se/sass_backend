<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerarDocumentoPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'formato' => ['required', 'in:A4,TICKET_80,TICKET_58,TODOS'],
        ];
    }
}
