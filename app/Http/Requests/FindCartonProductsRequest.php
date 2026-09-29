<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FindCartonProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'cartonNumber' => [
                'bail',
                'required_without:cartonID',
                'prohibited_with:cartonID',
                'string',
                'max:255',
            ],
            'cartonID' => [
                'bail',
                'required_without:cartonNumber',
                'prohibited_with:cartonNumber',
                'integer',
                'min:1',
            ],
        ];
    }
}
