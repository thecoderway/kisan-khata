<?php

namespace App\Modules\Customers\Presentation\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_code' => $this->customer_code,
            'name_original' => $this->name_original,
            'name_original_language' => $this->name_original_language,
            'name_hi' => $this->name_hi,
            'name_en' => $this->name_en,
            'mobile' => $this->mobile,
            'location' => [
                'state_code' => $this->state_code,
                'district_code' => $this->district_code,
                'subdistrict_code' => $this->subdistrict_code,
                'village_code' => $this->village_code,
            ],
            'address_original' => $this->address_original,
            'address_original_language' => $this->address_original_language,
            'address_hi' => $this->address_hi,
            'address_en' => $this->address_en,
            'notes' => $this->notes,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
