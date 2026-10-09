<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCartonEntriesRequest extends FormRequest
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
            'locationCode' => ['required', 'string', 'max:255'],
            'cartonNumber' => [
                'required',
                'regex:/\A(?:CTN[A-Z0-9]+|[0-9]{5,6})\z/i',
            ],
            'products' => ['required', 'array', 'min:1'],
            'products.*' => ['required', 'array:tpin,quantity'],
            'products.*.tpin' => ['required', 'string', 'max:255'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'imageToken' => ['nullable', 'string', 'max:4096'],
        ];
    }
}
