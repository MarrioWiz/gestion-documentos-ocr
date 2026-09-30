<?php

namespace App\Http\Requests;

use App\Models\Persona;
use App\Services\DocumentoOcrService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentoRequest extends FormRequest
{
    // Patrón oficial de 18 caracteres de la CURP.
    public const CURP_REGEX = '/^[A-Z][AEIOU][A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HM](AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QO|QR|SP|SL|SR|TC|TL|TS|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z\d]\d$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'curp' => ['required', 'string', 'regex:'.self::CURP_REGEX],
            'nombre_completo' => ['required', 'string', 'max:150'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'entidad_nacimiento' => ['nullable', Rule::in(DocumentoOcrService::ENTIDADES_CURP)],
            'tipo_documento' => ['required', Rule::in(Persona::TIPOS_DOCUMENTO)],
            'numero_documento' => ['nullable', 'string', 'max:100'],
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'texto_extraido' => ['nullable', 'string'],
            'reemplazar' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'curp.regex' => 'La CURP no tiene un formato válido (18 caracteres, patrón oficial).',
            'archivo.mimes' => 'El archivo debe ser JPG, PNG o PDF.',
            'archivo.max' => 'El archivo no puede pesar más de 10MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->curp) {
            $this->merge(['curp' => strtoupper(trim($this->curp))]);
        }
    }
}
