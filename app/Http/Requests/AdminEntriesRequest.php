<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->session()->get('admin_authenticated');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'zone' => ['required', 'in:small-parce,ltl,exceptions'],
            'status' => ['sometimes', 'in:all,pending,failed,success'],
            'type' => ['nullable', 'in:carton,sku,tpin'],
            'prefix' => ['nullable', 'in:S1,S2,S3,S4,S5,S6,N1,N2,N3,N4'],
            'location' => ['nullable', 'string', 'max:16'],
            'code' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'in:10,25,50,100'],
        ];
    }
}
