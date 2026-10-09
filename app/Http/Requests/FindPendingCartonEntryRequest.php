<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FindPendingCartonEntryRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'locationCode' => ['required', 'string', 'max:255'],
            'cartonNumber' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'locationCode' => strtoupper(trim((string) $this->input('locationCode'))),
            'cartonNumber' => strtoupper(trim((string) $this->input('cartonNumber'))),
        ]);
    }
}
