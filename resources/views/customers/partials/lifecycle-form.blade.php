@if ($errors->any())
    <div class="alert alert-danger" role="alert" tabindex="-1" data-drawer-error-summary>
        <strong>कार्रवाई पूरी नहीं हो सकी।</strong>
        <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form id="customer-form" method="POST" data-drawer-ajax action="{{ $action === 'deactivate'
    ? route('customers.deactivate', $customer)
    : ($action === 'reactivate'
        ? route('customers.reactivate', $customer)
        : ($action === 'soft-delete'
            ? route('customers.soft-delete', $customer)
            : route('customers.restore', $customer))) }}">
    @csrf
    @if ($action === 'soft-delete')
        @method('DELETE')
    @endif
    <input type="hidden" name="drawer_context" value="1">

    <div class="mb-3">
        <div class="text-secondary small mb-1">ग्राहक</div>
        <strong>{{ $customer->name_hi ?: $customer->name_original }}</strong>
    </div>

    @if (in_array($action, ['deactivate', 'soft-delete'], true))
        <div class="mb-3">
            <label for="lifecycle-reason" class="form-label">
                {{ $action === 'deactivate' ? 'निष्क्रिय करने का कारण' : 'Soft Delete करने का कारण' }}
                <span class="text-danger">*</span>
            </label>
            <textarea id="lifecycle-reason" name="reason" class="form-control" rows="3" maxlength="500" required>{{ old('reason') }}</textarea>
        </div>
    @elseif ($action === 'reactivate')
        <div class="mb-3">
            <label for="lifecycle-reason" class="form-label">पुनः सक्रिय करने का कारण (वैकल्पिक)</label>
            <textarea id="lifecycle-reason" name="reason" class="form-control" rows="3" maxlength="500">{{ old('reason') }}</textarea>
        </div>
    @elseif ($action === 'restore')
        <p class="mb-3">यह ग्राहक फिर से सक्रिय ग्राहक सूची में उपलब्ध होगा। क्या आप इसे Restore करना चाहते हैं?</p>
    @endif

    <div class="alert alert-warning py-2 mb-0" role="note">
        इस कार्रवाई की अनुमति और रिकॉर्ड सर्वर पर मौजूदा Customer Policy तथा lifecycle service द्वारा नियंत्रित होंगे।
    </div>
</form>
