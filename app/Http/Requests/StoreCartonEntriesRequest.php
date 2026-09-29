<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCartonEntriesRequest extends FormRequest
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
            '*' => ['required', 'array:cartonID,tpin,quantity'],
            '*.cartonID' => ['required', 'integer', 'min:1'],
            '*.tpin' => ['required', 'string', 'max:255'],
            '*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->all() === []) {
                    $validator->errors()->add('items', 'At least one carton product is required.');
                }
            },
        ];
    }
}
