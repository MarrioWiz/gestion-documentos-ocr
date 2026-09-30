<?php

namespace App\Http\Requests;

use App\Models\Persona;
use App\Services\Curp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentoRequest extends FormRequest
{
    public const MIMES = 'jpg,jpeg,png,webp,pdf';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'curp' => ['required', 'string', 'regex:'.Curp::regexValidacion()],
            'nombre_completo' => ['required', 'string', 'max:150'],
            'fecha_nacimiento' => ['nullable', 'date', 'before_or_equal:today'],
            'entidad_nacimiento' => ['nullable', Rule::in(Curp::ENTIDADES)],
            'tipo_documento' => ['required', Rule::in(Persona::TIPOS_DOCUMENTO)],
            'numero_documento' => ['nullable', 'string', 'max:100'],
            'archivo' => ['required', 'file', 'mimes:'.self::MIMES, 'max:10240'],
            'texto_extraido' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'curp.regex' => 'La CURP no tiene un formato válido (18 caracteres, patrón oficial).',
            'archivo.mimes' => 'El archivo debe ser JPG, PNG, WEBP o PDF.',
            'archivo.max' => 'El archivo no puede pesar más de 10MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'curp' => strtoupper(trim((string) $this->curp)),
            'nombre_completo' => mb_strtoupper(trim((string) $this->nombre_completo)),
        ]);
    }
}
