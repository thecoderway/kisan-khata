@if ($errors->any())
    <div class="alert alert-danger" role="alert" tabindex="-1" data-drawer-error-summary>
        <strong>जानकारी सेव नहीं हो सकी।</strong>
        <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@if ($drawerMode ?? false)
    <input type="hidden" name="drawer_context" value="1">
@endif

<form method="POST" action="{{ $customer ? route('customers.update', $customer) : route('customers.store') }}" id="customer-form" class="customer-drawer-form">
        @csrf
        @if ($customer) @method('PUT') @endif

        <section>
            <div class="form-section-heading"><i class="ti ti-user"></i><div><h2>मुख्य जानकारी</h2><p>ग्राहक का नाम और संपर्क विवरण।</p></div></div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name_original" class="form-label">ग्राहक का नाम <span class="text-danger">*</span></label>
                    <input id="name_original" name="name_original" value="{{ old('name_original', $customer?->name_original) }}" class="form-control" maxlength="255" required>
                </div>
                <div class="col-md-6">
                    <label for="name_original_language" class="form-label">नाम किस भाषा में लिखा है? <span class="text-danger">*</span></label>
                    <select id="name_original_language" name="name_original_language" class="form-select" required>
                        <option value="hi" @selected(old('name_original_language', $customer?->name_original_language ?? 'hi') === 'hi')>हिंदी</option>
                        <option value="en" @selected(old('name_original_language', $customer?->name_original_language) === 'en')>English</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="mobile" class="form-label">मोबाइल नंबर</label>
                    <input id="mobile" name="mobile" value="{{ old('mobile', $customer?->mobile) }}" class="form-control" maxlength="20" inputmode="tel">
                </div>
                <div class="col-md-6">
                    <label for="customer_code" class="form-label">ग्राहक कोड</label>
                    <input id="customer_code" value="{{ $customer?->customer_code ?: 'स्वचालित रूप से निर्धारित नहीं' }}" class="form-control" disabled>
                    <div class="form-hint">ग्राहक कोड का format अभी निर्धारित नहीं है।</div>
                </div>
            </div>
        </section>

        <hr class="my-4">
        <section>
            <div class="form-section-heading"><i class="ti ti-language"></i><div><h2>अनुवाद</h2><p>मूल नाम सुरक्षित रहता है। हिंदी और अंग्रेज़ी रूप अलग से संपादित कर सकते हैं।</p></div></div>
            <div class="row g-3">
                <div class="col-md-6"><label for="name_hi" class="form-label">हिंदी नाम</label><input id="name_hi" name="name_hi" value="{{ old('name_hi', $customer?->name_hi) }}" class="form-control" maxlength="255"></div>
                <div class="col-md-6"><label for="name_en" class="form-label">English name</label><input id="name_en" name="name_en" value="{{ old('name_en', $customer?->name_en) }}" class="form-control" maxlength="255"></div>
            </div>
            <div class="form-hint mt-2">अनुवाद अपने-आप नहीं बनाया जाता। मूल नाम को बदलने के लिए ऊपर का मूल नाम field संपादित करें।</div>
        </section>

        <hr class="my-4">
        <section
            x-data="customerLocation(@js([
                'state_code' => old('state_code', $customer?->state_code ?? ($locationDefaults['state_code'] ?? null)),
                'district_code' => old('district_code', $customer?->district_code ?? ($locationDefaults['district_code'] ?? null)),
                'subdistrict_code' => old('subdistrict_code', $customer?->subdistrict_code ?? ($locationDefaults['subdistrict_code'] ?? null)),
                'village_code' => old('village_code', $customer?->village_code ?? ($locationDefaults['village_code'] ?? null)),
            ]), @js(url('/shop-profile/locations/states')))"
        >
            <div class="form-section-heading"><i class="ti ti-map-pin"></i><div><h2>ग्राहक का स्थान</h2><p>दुकान का स्थान शुरुआती सुझाव है। ग्राहक का वास्तविक स्थान अलग चुन सकते हैं या खाली छोड़ सकते हैं।</p></div></div>
            <div class="alert alert-info py-2">अभी आधिकारिक LGD गाँव डेटा मध्य प्रदेश के लिए उपलब्ध है। ग्राहक का स्थान दुकान के स्थान से स्वतंत्र रूप से सेव होगा।</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="state_code" class="form-label">राज्य</label>
                    <select id="state_code" name="state_code" class="form-select" x-model="stateCode" @change="loadDistricts()" >
                        <option value="">राज्य चुनें</option>
                        <template x-for="item in states" :key="item.code"><option :value="item.code" x-text="label(item)"></option></template>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="district_code" class="form-label">जिला</label>
                    <select id="district_code" name="district_code" class="form-select" x-model="districtCode" @change="loadSubdistricts()" :disabled="!stateCode">
                        <option value="">जिला चुनें</option>
                        <template x-for="item in districts" :key="item.code"><option :value="item.code" x-text="label(item)"></option></template>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="subdistrict_code" class="form-label">तहसील / उप-जिला</label>
                    <select id="subdistrict_code" name="subdistrict_code" class="form-select" x-model="subdistrictCode" @change="loadVillages()" :disabled="!districtCode">
                        <option value="">तहसील चुनें</option>
                        <template x-for="item in subdistricts" :key="item.code"><option :value="item.code" x-text="label(item)"></option></template>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="village_code" class="form-label">गाँव</label>
                    <select id="village_code" name="village_code" class="form-select" x-model="villageCode" :disabled="!subdistrictCode">
                        <option value="">गाँव चुनें</option>
                        <template x-for="item in villages" :key="item.code"><option :value="item.code" x-text="label(item)"></option></template>
                    </select>
                </div>
            </div>
            <p class="text-danger small mt-2" role="alert" x-show="error" x-text="error"></p>
            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" @click="clearLocation()">स्थान साफ़ करें</button>
        </section>

        <hr class="my-4">
        <section>
            <div class="form-section-heading"><i class="ti ti-notes"></i><div><h2>पता और नोट्स</h2><p>मूल पता सुरक्षित रखें; अनुवाद अलग fields में दर्ज करें।</p></div></div>
            <div class="row g-3">
                <div class="col-12"><label for="address_original" class="form-label">मूल पता</label><textarea id="address_original" name="address_original" class="form-control" rows="2" maxlength="2000">{{ old('address_original', $customer?->address_original) }}</textarea></div>
                <div class="col-md-6"><label for="address_original_language" class="form-label">पते की मूल भाषा</label><select id="address_original_language" name="address_original_language" class="form-select"><option value="">चुनें</option><option value="hi" @selected(old('address_original_language', $customer?->address_original_language) === 'hi')>हिंदी</option><option value="en" @selected(old('address_original_language', $customer?->address_original_language) === 'en')>English</option></select></div>
                <div class="col-md-6"><label for="notes" class="form-label">नोट्स</label><textarea id="notes" name="notes" class="form-control" rows="2" maxlength="5000">{{ old('notes', $customer?->notes) }}</textarea></div>
                <div class="col-md-6"><label for="address_hi" class="form-label">हिंदी पता</label><textarea id="address_hi" name="address_hi" class="form-control" rows="2" maxlength="2000">{{ old('address_hi', $customer?->address_hi) }}</textarea></div>
                <div class="col-md-6"><label for="address_en" class="form-label">English address</label><textarea id="address_en" name="address_en" class="form-control" rows="2" maxlength="2000">{{ old('address_en', $customer?->address_en) }}</textarea></div>
            </div>
        </section>

    </form>
