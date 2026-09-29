<?php

namespace App\Http\Requests;

use App\Models\Entry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecognizeImageRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in([Entry::TYPE_CARTON, Entry::TYPE_TPIN])],
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,bmp,gif,webp', 'max:10240'],
        ];
    }
}
