<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductEntriesRequest extends FormRequest
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
            'tpin' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
