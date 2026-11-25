export function registerCustomerLocation(Alpine) {
    Alpine.data('customerLocation', (initial = {}, baseUrl = '') => ({
        stateCode: initial.state_code || '',
        districtCode: initial.district_code || '',
        subdistrictCode: initial.subdistrict_code || '',
        villageCode: initial.village_code || '',
        states: [],
        districts: [],
        subdistricts: [],
        villages: [],
        error: '',
        baseUrl,
        requestIds: { states: 0, districts: 0, subdistricts: 0, villages: 0 },

        async options(level, url) {
            const requestId = ++this.requestIds[level];
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('स्थान की सूची लोड नहीं हो सकी। दोबारा प्रयास करें।');
                const payload = await response.json();
                if (requestId !== this.requestIds[level]) return null;
                this.error = '';
                return payload.data;
            } catch (error) {
                if (requestId === this.requestIds[level]) this.error = error.message;
                return null;
            }
        },

        async init() {
            const data = await this.options('states', this.baseUrl);
            if (data) this.states = data;
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
            this.requestIds.districts++;
            this.requestIds.subdistricts++;
            this.requestIds.villages++;
            this.districts = [];
            if (!this.stateCode) return;
            const data = await this.options('districts', this.baseUrl + '/' + encodeURIComponent(this.stateCode) + '/districts');
            if (data) this.districts = data;
            if (!reset && this.districtCode) await this.loadSubdistricts(false);
        },

        async loadSubdistricts(reset = true) {
            if (reset) {
                this.subdistrictCode = '';
                this.villageCode = '';
                this.villages = [];
            }
            this.requestIds.subdistricts++;
            this.requestIds.villages++;
            this.subdistricts = [];
            if (!this.stateCode || !this.districtCode) return;
            const url = this.baseUrl + '/' + encodeURIComponent(this.stateCode) + '/districts/' + encodeURIComponent(this.districtCode) + '/subdistricts';
            const data = await this.options('subdistricts', url);
            if (data) this.subdistricts = data;
            if (!reset && this.subdistrictCode) await this.loadVillages(false);
        },

        async loadVillages(reset = true) {
            if (reset) this.villageCode = '';
            this.requestIds.villages++;
            this.villages = [];
            if (!this.stateCode || !this.districtCode || !this.subdistrictCode) return;
            const url = this.baseUrl + '/' + encodeURIComponent(this.stateCode) + '/districts/' + encodeURIComponent(this.districtCode) + '/subdistricts/' + encodeURIComponent(this.subdistrictCode) + '/villages';
            const data = await this.options('villages', url);
            if (data) this.villages = data;
        },

        clearLocation() {
            this.stateCode = '';
            this.districtCode = '';
            this.subdistrictCode = '';
            this.villageCode = '';
            this.districts = [];
            this.subdistricts = [];
            this.villages = [];
            Object.keys(this.requestIds).forEach((key) => this.requestIds[key]++);
            this.error = '';
        },

        label(item) {
            return item.local_name ? item.local_name + ' (' + item.name + ')' : item.name;
        },
    }));
}
