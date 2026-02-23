<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'phone'      => ['required', 'string', 'max:20', 'regex:/^[+\d\s\-\(\)]+$/'],
            'email'      => ['required', 'email', 'max:255', 'unique:clients,email'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es obligatorio.',
            'first_name.max'      => 'El nombre no puede superar los 100 caracteres.',
            'last_name.required'  => 'El apellido es obligatorio.',
            'last_name.max'       => 'El apellido no puede superar los 100 caracteres.',
            'phone.required'      => 'El teléfono es obligatorio.',
            'phone.max'           => 'El teléfono no puede superar los 20 caracteres.',
            'phone.regex'         => 'El teléfono solo puede contener números, +, -, espacios, ( y ).',
            'email.required'      => 'El email es obligatorio.',
            'email.email'         => 'El email no tiene un formato válido.',
            'email.unique'        => 'Ya existe un cliente registrado con este email.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
