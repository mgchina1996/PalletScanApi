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
            'locationCode' => [
                'required',
                'string',
                'regex:/\AS[1-6]-A(?:[1-9]|1[0-5])-[A-E][12]\z/',
            ],
            'cartonNumber' => [
                'required',
                'regex:/\A(?:CTN[A-Z0-9]+|[0-9]{5,6})\z/i',
            ],
            'products' => ['required', 'array', 'min:1'],
            'products.*' => ['required', 'array:tpin,quantity'],
            'products.*.tpin' => ['required', 'string', 'max:255'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
