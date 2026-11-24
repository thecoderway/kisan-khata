@extends('layouts.app')

@section('title', 'दुकान की जानकारी')
@section('page-title', 'दुकान की जानकारी')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">SHOP SETTINGS</span>
            <h1>दुकान की जानकारी</h1>
            <p>दुकान की सामान्य जानकारी और आधिकारिक गाँव-स्थान अपडेट करें।</p>
        </div>
        <span class="status-pill"><i class="ti ti-shield-check"></i> Owner access</span>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>जानकारी सेव नहीं हो सकी।</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('shop-profile.update') }}" class="content-card form-card shop-profile-form">
        @csrf
        @method('PUT')

        <section>
            <div class="form-section-heading">
                <i class="ti ti-building-store"></i>
                <div>
                    <h2>दुकान की सामान्य जानकारी</h2>
                    <p>यह जानकारी आपकी दुकान के प्रोफाइल में दिखाई देगी।</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">दुकान का नाम <span class="text-danger">*</span></label>
                    <input id="name" name="name" value="{{ old('name', $shop->name) }}" class="form-control" maxlength="255" required>
                </div>
                <div class="col-md-6">
                    <label for="owner_name" class="form-label">मालिक का नाम <span class="text-danger">*</span></label>
                    <input id="owner_name" name="owner_name" value="{{ old('owner_name', $shop->owner_name) }}" class="form-control" maxlength="255" required>
                </div>
                <div class="col-md-6">
                    <label for="mobile" class="form-label">मोबाइल नंबर <span class="text-danger">*</span></label>
                    <input id="mobile" name="mobile" value="{{ old('mobile', $shop->mobile) }}" class="form-control" maxlength="20" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">ईमेल</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $shop->email) }}" class="form-control" maxlength="255">
                </div>
                <div class="col-md-8">
                    <label for="address" class="form-label">दुकान का पता</label>
                    <input id="address" name="address" value="{{ old('address', $shop->address) }}" class="form-control" maxlength="255">
                </div>
                <div class="col-md-4">
                    <label for="pincode" class="form-label">पिन कोड</label>
                    <input id="pincode" name="pincode" value="{{ old('pincode', $shop->pincode) }}" class="form-control" maxlength="12">
                </div>
            </div>
        </section>

        <hr class="my-4">

        <section
            x-data="{
                stateCode: @js(old('state_code', $shop->state_code)),
                districtCode: @js(old('district_code', $shop->district_code)),
                subdistrictCode: @js(old('subdistrict_code', $shop->subdistrict_code)),
                villageCode: @js(old('village_code', $shop->village_code)),
                states: [],
                districts: [],
                subdistricts: [],
                villages: [],
                baseUrl: @js(url('/shop-profile/locations/states')),
                async getOptions(url) {
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('Location options could not be loaded.');
                    const payload = await response.json();
                    return payload.data;
                },
                async loadStates() {
                    this.states = await this.getOptions(this.baseUrl);
                    if (this.stateCode) await this.loadDistricts(false);
                },
                async loadDistricts(reset = true) {
                    if (reset) {
                        this.districtCode = '';
                        this.subdistrictCode = '';
                        this.villageCode = '';
                        this.subdistricts = [];
                        this.villages = [];
                    }
                    this.districts = this.stateCode
                        ? await this.getOptions(this.baseUrl + '/' + encodeURIComponent(this.stateCode) + '/districts')
                        : [];
                    if (!reset && this.districtCode) await this.loadSubdistricts(false);
                },
                async loadSubdistricts(reset = true) {
                    if (reset) {
                        this.subdistrictCode = '';
                        this.villageCode = '';
                        this.villages = [];
                    }
                    this.subdistricts = this.stateCode && this.districtCode
                        ? await this.getOptions(this.baseUrl + '/' + encodeURIComponent(this.stateCode) + '/districts/' + encodeURIComponent(this.districtCode) + '/subdistricts')
                        : [];
                    if (!reset && this.subdistrictCode) await this.loadVillages(false);
                },
                async loadVillages(reset = true) {
                    if (reset) this.villageCode = '';
                    this.villages = this.stateCode && this.districtCode && this.subdistrictCode
                        ? await this.getOptions(this.baseUrl + '/' + encodeURIComponent(this.stateCode) + '/districts/' + encodeURIComponent(this.districtCode) + '/subdistricts/' + encodeURIComponent(this.subdistrictCode) + '/villages')
                        : [];
                },
                label(item) {
                    return item.local_name ? item.local_name + ' (' + item.name + ')' : item.name;
                }
            }"
            x-init="loadStates()"
        >
            <div class="form-section-heading">
                <i class="ti ti-map-pin"></i>
                <div>
                    <h2>दुकान का स्थान</h2>
                    <p>State → District → Tehsil → Village चुनें। हर स्तर पर विकल्प पिछले चयन के अनुसार आएँगे।</p>
                </div>
            </div>
            <div class="alert alert-info py-2">
                अभी उपलब्ध आधिकारिक LGD गाँव डेटा मध्य प्रदेश के लिए है। स्थान बदलने पर पुराने ग्राहक रिकॉर्ड नहीं बदलेंगे।
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="state_code" class="form-label">राज्य</label>
                    <select id="state_code" name="state_code" class="form-select" x-model="stateCode" @change="loadDistricts()">
                        <option value="">राज्य चुनें</option>
                        <template x-for="item in states" :key="item.code">
                            <option :value="item.code" x-text="label(item)"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="district_code" class="form-label">जिला</label>
                    <select id="district_code" name="district_code" class="form-select" x-model="districtCode" @change="loadSubdistricts()" :disabled="!stateCode">
                        <option value="">जिला चुनें</option>
                        <template x-for="item in districts" :key="item.code">
                            <option :value="item.code" x-text="label(item)"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="subdistrict_code" class="form-label">तहसील / उप-जिला</label>
                    <select id="subdistrict_code" name="subdistrict_code" class="form-select" x-model="subdistrictCode" @change="loadVillages()" :disabled="!districtCode">
                        <option value="">तहसील चुनें</option>
                        <template x-for="item in subdistricts" :key="item.code">
                            <option :value="item.code" x-text="label(item)"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="village_code" class="form-label">गाँव</label>
                    <select id="village_code" name="village_code" class="form-select" x-model="villageCode" :disabled="!subdistrictCode">
                        <option value="">गाँव चुनें</option>
                        <template x-for="item in villages" :key="item.code">
                            <option :value="item.code" x-text="label(item)"></option>
                        </template>
                    </select>
                </div>
            </div>
        </section>

        @if ($shop->village || $shop->tehsil || $shop->district || $shop->state)
            <div class="alert alert-secondary mt-4 mb-0">
                <strong>पुराना स्थान विवरण (केवल संदर्भ के लिए):</strong>
                {{ collect([$shop->village, $shop->tehsil, $shop->district, $shop->state])->filter()->implode(', ') }}
                <div class="small mt-1">यह legacy जानकारी सुरक्षित रखी गई है; नए LGD चयन से इसे overwrite नहीं किया जाएगा।</div>
            </div>
        @endif

        <div class="form-actions">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">वापस जाएँ</a>
            <button type="submit" class="btn btn-primary kk-primary">जानकारी सेव करें <i class="ti ti-check ms-1"></i></button>
        </div>
    </form>
@endsection
