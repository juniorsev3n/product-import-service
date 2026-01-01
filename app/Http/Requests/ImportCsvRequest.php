<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCsvRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:' . config('import.max_file_size', 5120), // KB
            ],
        ];
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'file.required' => 'File CSV wajib diunggah.',
            'file.file'     => 'Input harus berupa file.',
            'file.mimes'    => 'Format file harus CSV.',
            'file.max'      => 'Ukuran file terlalu besar.',
        ];
    }
}
