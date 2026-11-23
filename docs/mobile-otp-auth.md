# Mobile OTP API

Package 2C adds bearer-token authentication for native clients. Existing browser email/password session authentication remains unchanged.

## Endpoints

### Request OTP

`POST /api/auth/mobile/request-otp`

JSON body:

```json
{
  "mobile": "9876543210"
}
```

The endpoint returns a generic `202` response whether or not the mobile belongs to an eligible account. This avoids exposing account existence. OTP delivery must be configured before the endpoint can send messages; the default null sender fails closed and returns `503`.

### Verify OTP

`POST /api/auth/mobile/verify-otp`

JSON body:

```json
{
  "mobile": "9876543210",
  "otp": "123456"
}
```

A successful response contains a Sanctum bearer token, expiry timestamp, and the authenticated user's shop/role context. The OTP is never included in the API response.

### Authenticated user

`GET /api/user`

Header: `Authorization: Bearer <token>`

### Logout

`POST /api/logout`

Revokes only the token used for the request.

## Security behavior

- Six-digit cryptographically generated OTP; only a password hash is stored.
- Five-minute expiry, single-use challenge, and five verification attempts.
- Sixty-second resend cooldown.
- Per-mobile and per-IP request rate limits, plus per-mobile verification rate limiting.
- Formatting separators (spaces, parentheses, and hyphens) are removed for rate-limit identity, preventing trivial formatting variants from bypassing throttles.
- OTP login is limited to active users belonging to active shops.
- Mobile verification timestamp is set only after successful OTP verification.
- Tokens expire after 30 days by default; configure `MOBILE_OTP_TOKEN_EXPIRY_DAYS`.
- Expired and consumed challenge records are pruned daily after seven days by the `mobile-otp:prune` scheduled command. Ensure Laravel's scheduler is running in the deployment environment.
- No OTP is returned in API responses or written to application logs.
- Default sender is intentionally unconfigured. Implement `App\\Contracts\\MobileOtpSender` and set `MOBILE_OTP_SENDER` to its fully qualified class name to enable SMS delivery. Provider implementations should throw `App\\Exceptions\\MobileOtpDeliveryUnavailable` when delivery fails.

## Mobile number format

Both OTP endpoints validate the same mobile input format accepted by owner registration. Account lookup continues to use the exact stored mobile string. Keep registration and mobile-app input formatting consistent; a canonical E.164 storage policy should be introduced separately with a deliberate migration before changing account lookup semantics.
