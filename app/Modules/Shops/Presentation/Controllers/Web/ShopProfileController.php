<?php

namespace App\Modules\Shops\Presentation\Controllers\Web;

use App\Models\User;
use App\Tenancy\CurrentShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShopProfileController
{
    public function edit(Request $request, CurrentShop $currentShop): View
    {
        $this->authorizeOwner($request);

        return view('shops.profile.edit', ['shop' => $currentShop->require()]);
    }

    public function update(Request $request, CurrentShop $currentShop): RedirectResponse
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\\-\\s]{8,20}$/'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'pincode' => ['nullable', 'string', 'max:12', 'regex:/^[0-9A-Za-z\\-\\s]{3,12}$/'],
            'state_code' => ['nullable', 'string', 'max:20', Rule::exists('villages', 'stateCode')],
            'district_code' => [
                'nullable', 'string', 'max:20',
                Rule::exists('villages', 'districtCode')
                    ->where(fn ($query) => $query->where('stateCode', $request->input('state_code'))),
            ],
            'subdistrict_code' => [
                'nullable', 'string', 'max:20',
                Rule::exists('villages', 'subdistrictCode')
                    ->where(fn ($query) => $query->where('stateCode', $request->input('state_code'))
                        ->where('districtCode', $request->input('district_code'))),
            ],
            'village_code' => [
                'nullable', 'string', 'max:20',
                Rule::exists('villages', 'villageCode')
                    ->where(fn ($query) => $query->where('stateCode', $request->input('state_code'))
                        ->where('districtCode', $request->input('district_code'))
                        ->where('subdistrictCode', $request->input('subdistrict_code'))),
            ],
        ]);

        $shop = $currentShop->require();

        // A changed parent must not leave omitted descendants pointing to the old hierarchy.
        // Explicitly supplied descendants are retained only after hierarchy-scoped validation.
        if (array_key_exists('state_code', $validated)
            && $validated['state_code'] !== $shop->state_code) {
            $validated += [
                'district_code' => null,
                'subdistrict_code' => null,
                'village_code' => null,
            ];
        } elseif (array_key_exists('district_code', $validated)
            && $validated['district_code'] !== $shop->district_code) {
            $validated += [
                'subdistrict_code' => null,
                'village_code' => null,
            ];
        } elseif (array_key_exists('subdistrict_code', $validated)
            && $validated['subdistrict_code'] !== $shop->subdistrict_code) {
            $validated += ['village_code' => null];
        }

        $shop->fill($validated)->save();

        return redirect()->route('shop-profile.edit')
            ->with('status', 'दुकान की जानकारी सफलतापूर्वक अपडेट हो गई है।');
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()?->role === User::ROLE_OWNER, 403);
    }
}
