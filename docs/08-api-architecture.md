
# Kisan Khata — API Architecture

**Document Version:** 1.0  
**API Style:** REST  
**Initial API Version:** v1  
**Backend:** Laravel

---

## 1. Purpose

Kisan Khata will launch as a responsive web application.

A native Android application is planned for Phase 2.

The backend must therefore separate business logic from the web interface.

Both web and Android clients must use the same financial services.

## 2. API Principles

1. Use REST conventions.
2. Use versioned API routes.
3. Return consistent JSON responses.
4. Authenticate every protected request.
5. Validate tenant membership.
6. Enforce permissions.
7. Validate input server-side.
8. Paginate collection endpoints.
9. Never expose unnecessary database fields.
10. Keep accounting logic on the backend.

## 3. API Base Path

Proposed:

`/api/v1`

Examples:

- `/api/v1/me`
- `/api/v1/shops`
- `/api/v1/customers`
- `/api/v1/sales`
- `/api/v1/payments`
- `/api/v1/ledger`

## 4. Authentication

Use an authentication mechanism suitable for the web and Android clients.

Possible options:

- Laravel Sanctum for first-party API authentication.
- Another approved token strategy if future requirements justify it.

The web session and Android token authentication flows should be configured separately as appropriate.

Never store long-lived access tokens insecurely in the Android application.

The API must validate the user's active shop membership.

## 5. Shop Context

A user may belong to more than one shop.

The API must resolve the selected shop through authenticated membership.

A shop identifier may be provided as a context selector, but it must be checked against the authenticated user's active memberships.

Never trust an arbitrary shop_id.

## 6. Response Format

Use a consistent success response.

Example:

```json
{
  "success": true,
  "message": "Customer created successfully",
  "data": {
    "id": 123,
    "name": "Example Customer"
  }
}
```

Use a consistent error response.

Example:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "mobile": [
      "The mobile field is required."
    ]
  }
}
```

The exact response contract must be documented and tested.

## 7. Pagination

Collection endpoints should support pagination.

Example:

`GET /api/v1/customers?page=1&per_page=20`

Return:

- Data
- Current page
- Per-page count
- Total records
- Last page

Apply maximum page-size limits.

## 8. Customer Endpoints

Suggested endpoints:

### List Customers

`GET /api/v1/customers`

Supports:

- Search
- Village filter
- Pagination

### Create Customer

`POST /api/v1/customers`

### View Customer

`GET /api/v1/customers/{customer}`

### Update Customer

`PUT /api/v1/customers/{customer}`

### Customer Ledger

`GET /api/v1/customers/{customer}/ledger`

The API must enforce tenant ownership and permissions.

## 9. Sales Endpoints

Suggested endpoints:

- `GET /api/v1/sales`
- `POST /api/v1/sales`
- `GET /api/v1/sales/{sale}`
- `PUT /api/v1/sales/{sale}`
- `POST /api/v1/sales/{sale}/post`
- `POST /api/v1/sales/{sale}/reverse`

Draft updates and posted transaction corrections must follow the financial integrity policy.

## 10. Payment Endpoints

Suggested endpoints:

- `GET /api/v1/payments`
- `POST /api/v1/payments`
- `GET /api/v1/payments/{payment}`
- `POST /api/v1/payments/{payment}/post`
- `POST /api/v1/payments/{payment}/reverse`
- `GET /api/v1/payments/{payment}/allocations`

Every payment operation must be idempotent where appropriate.

## 11. Ledger Endpoints

Suggested endpoints:

- `GET /api/v1/customers/{customer}/ledger`
- `GET /api/v1/customers/{customer}/balance`
- `GET /api/v1/customers/{customer}/ledger/print`

Query parameters may include:

- from_date
- to_date
- page
- per_page

Ledger calculations must be performed by backend services.

## 12. Interest Endpoints

Suggested endpoints:

- `GET /api/v1/interest/policies`
- `POST /api/v1/interest/policies`
- `GET /api/v1/interest/policies/{policy}`
- `POST /api/v1/interest/policies/{policy}/versions`
- `GET /api/v1/customers/{customer}/interest`

Only authorized users may configure or approve interest policies.

Do not expose an endpoint that allows clients to arbitrarily post interest amounts without server-side calculation and validation.

## 13. Settlement Endpoints

Suggested endpoints:

- `GET /api/v1/settlements`
- `POST /api/v1/settlements`
- `GET /api/v1/settlements/{settlement}`
- `POST /api/v1/settlements/{settlement}/post`
- `POST /api/v1/settlements/{settlement}/reverse`

Settlement rules must be enforced by backend services.

## 14. File Upload API

Bill and receipt uploads must:

- Use authenticated endpoints.
- Validate file size and type.
- Store files privately.
- Associate files with tenant-owned records.
- Require authorization for download.

Do not return permanent public file URLs for private financial documents.

## 15. Idempotency

Financial endpoints should support an idempotency key.

Example request header:

`Idempotency-Key: unique-client-generated-value`

The server must:

1. Identify the key within the correct tenant and user context.
2. Verify that a repeated request has the same request payload.
3. Return the original result for a valid retry.
4. Reject reuse of the same key with a different payload.
5. Prevent duplicate financial posting.

The implementation must handle concurrent requests safely.

## 16. API Security

- Authentication.
- Tenant membership validation.
- Authorization policies.
- Rate limiting.
- Input validation.
- Request size limits.
- Safe error responses.
- Secure file handling.
- Audit logging.

## 17. API Resources

Use Laravel API Resources or an equivalent transformation layer.

Do not expose Eloquent models directly without controlling their serialized fields.

Avoid returning:

- Password hashes.
- Internal tokens.
- Unnecessary personal data.
- Internal security fields.
- Unrestricted tenant metadata.

## 18. Future Android Integration

Android application responsibilities:

- Authentication UI.
- Shop selection UI.
- Customer search.
- Customer forms.
- Sale forms.
- Payment forms.
- Ledger display.
- Bill and receipt upload.
- Language selection.

Backend responsibilities:

- Interest calculation.
- Payment allocation.
- Financial posting.
- Ledger generation.
- Settlement processing.
- Authorization.
- Tenant isolation.

The Android application must not independently calculate authoritative financial balances.

## 19. API Compatibility

Once an API contract is released:

- Avoid breaking existing clients.
- Add optional fields where practical.
- Use a new API version for breaking changes.
- Document deprecated endpoints.
- Maintain automated API tests.

## 20. Offline Support

Offline synchronization is not part of the initial release.

Do not implement local financial posting as authoritative.

If offline support is added later, it requires a separate conflict-resolution and synchronization design.

## 21. API Testing

Test:

- Authentication.
- Shop membership.
- Tenant isolation.
- Permissions.
- Validation.
- Pagination.
- Customer endpoints.
- Sales endpoints.
- Payment endpoints.
- Idempotency.
- Ledger endpoints.
- File uploads.
- Interest endpoints.
- Settlement endpoints.

## 22. Acceptance Criteria

The API architecture is acceptable when:

- Routes are versioned.
- Responses are consistent.
- Business logic is shared with web application services.
- Tenant isolation is enforced.
- Financial endpoints are safe against duplicate requests.
- API tests pass.
- Android can use the same backend without duplicating accounting logic.
