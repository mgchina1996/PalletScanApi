<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FindCartonProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'cartonNumber' => [
                'bail',
                'required_without:cartonID',
                Rule::prohibitedIf(fn (): bool => $this->filled('cartonID')),
                'string',
                'max:255',
            ],
            'cartonID' => [
                'bail',
                'required_without:cartonNumber',
                Rule::prohibitedIf(fn (): bool => $this->filled('cartonNumber')),
                'integer',
                'min:1',
            ],
        ];
    }
}
