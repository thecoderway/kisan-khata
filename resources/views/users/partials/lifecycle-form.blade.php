@if ($errors->any())
    <div class="alert alert-danger" role="alert" tabindex="-1" data-drawer-error-summary>
        <strong>कार्रवाई पूरी नहीं हो सकी।</strong>
        <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
<form id="user-drawer-form" method="POST" action="{{ $action === 'deactivate' ? route('users.deactivate', $member->id) : ($action === 'reactivate' ? route('users.reactivate', $member->id) : route('users.role', $member->id)) }}" data-drawer-ajax>
    @csrf
    @if ($action === 'role') @method('PATCH') @endif
    <input type="hidden" name="drawer_context" value="1">
    <div class="mb-3"><div class="text-secondary small mb-1">उपयोगकर्ता</div><strong>{{ $member->name }}</strong><div class="text-secondary small">{{ $member->role === 'manager' ? 'प्रबंधक' : 'कर्मचारी' }} · {{ $member->is_active ? 'सक्रिय' : 'निष्क्रिय' }}</div></div>
    @if ($action === 'role')
        <div class="mb-3">
            <label for="user-new-role" class="form-label">नई भूमिका <span class="text-danger">*</span></label>
            <select id="user-new-role" name="role" class="form-select" required>
                <option value="manager" @selected($member->role === 'manager')>प्रबंधक</option>
                <option value="staff" @selected($member->role === 'staff')>कर्मचारी</option>
            </select>
        </div>
    @else
        <p>{{ $action === 'deactivate' ? 'इस खाते की सभी मोबाइल टोकन और डेटाबेस सत्र निरस्त कर दी जाएँगी।' : 'यह खाता फिर से सक्रिय हो जाएगा और क्षमता सीमा लागू रहेंगी।' }}</p>
    @endif
    <div class="mb-3">
        <label for="user-lifecycle-reason" class="form-label">कारण (वैकल्पिक)</label>
        <textarea id="user-lifecycle-reason" name="reason" class="form-control" rows="3" maxlength="500" placeholder="इस बदलाव का कारण दर्ज करें">{{ old('reason') }}</textarea>
    </div>
    <div class="alert alert-warning py-2 mb-0" role="note">कार्रवाई सर्वर की अनुमति जाँच, दुकान का डेटा अलगाव और क्षमता जाँच के अधीन होगी। लेखा-जाँच रिकॉर्ड केवल जोड़ा जा सकने वाला रहेगा।</div>
</form>