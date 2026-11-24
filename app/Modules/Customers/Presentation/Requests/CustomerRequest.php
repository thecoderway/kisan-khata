<?php

namespace App\Modules\Customers\Presentation\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            ? $this->user()?->can('update', $customer) ?? false
            : ($this->user()?->can('create', Customer::class) ?? false);
    }

    public function rules(): array
    {
        return [
            'name_original' => ['required', 'string', 'max:255'],
            'name_original_language' => ['required', 'string', Rule::in(['hi', 'en'])],
            'name_hi' => ['nullable', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+()\\-\\s]{8,20}$/'],
            'state_code' => ['nullable', 'string', 'max:20', Rule::exists('villages', 'stateCode')],
            'district_code' => [
                'nullable', 'string', 'max:20',
                Rule::exists('villages', 'districtCode')
                    ->where(fn ($query) => $query->where('stateCode', $this->input('state_code'))),
            ],
            'subdistrict_code' => [
                'nullable', 'string', 'max:20',
                Rule::exists('villages', 'subdistrictCode')
                    ->where(fn ($query) => $query->where('stateCode', $this->input('state_code'))
                        ->where('districtCode', $this->input('district_code'))),
            ],
            'village_code' => [
                'nullable', 'string', 'max:20',
                Rule::exists('villages', 'villageCode')
                    ->where(fn ($query) => $query->where('stateCode', $this->input('state_code'))
                        ->where('districtCode', $this->input('district_code'))
                        ->where('subdistrictCode', $this->input('subdistrict_code'))),
            ],
            'address_original' => ['nullable', 'string', 'max:2000'],
            'address_original_language' => ['nullable', 'string', Rule::in(['hi', 'en'])],
            'address_hi' => ['nullable', 'string', 'max:2000'],
            'address_en' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
