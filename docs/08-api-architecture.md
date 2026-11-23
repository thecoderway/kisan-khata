# Kisan Khata — API Architecture

**Document Version:** 2.0  
**Status:** Approved  
**API Style:** REST  
**Initial API Version:** v1  
**Backend:** Laravel 13  
**Database:** MySQL 8.4  
**PHP:** 8.3

---

## 1. Purpose

Kisan Khata will initially launch as a responsive web application. A native Android application is planned for a later phase.

The backend architecture must allow both clients to use the same business logic, accounting engine, authorization rules and tenant-isolation mechanisms.

The API will provide a stable interface for future Android integration without duplicating financial calculations in the client applications.

## 2. API Architecture Principles

1. Follow REST conventions.
2. Use versioned API routes.
3. Return consistent JSON responses.
4. Keep business and accounting logic in backend services.
5. Authenticate every protected request.
6. Enforce shop-level tenant isolation.
7. Enforce role-based authorization.
8. Validate all input on the server.
9. Paginate collection endpoints.
10. Use API Resources to control response fields.
11. Prevent duplicate financial transactions through idempotency.
12. Maintain audit history for sensitive operations.
13. Keep the API compatible with future Android integration.
14. Do not make the client authoritative for financial balances.

## 3. API Base Path

Initial API prefix:

`/api/v1`

Suggested route groups:

- `/api/v1/auth`
- `/api/v1/me`
- `/api/v1/shop`
- `/api/v1/users`
- `/api/v1/customers`
- `/api/v1/sales`
- `/api/v1/payments`
- `/api/v1/ledger`
- `/api/v1/interest`
- `/api/v1/settlements`
- `/api/v1/reports`
- `/api/v1/subscription`
- `/api/v1/files`

The final route definitions will be maintained in the API implementation and corresponding API documentation.

## 4. Authentication

Authentication must support the product's approved login methods:

- Mobile OTP.
- Email and password.

Laravel Sanctum is the preferred authentication mechanism for the initial implementation.

Authentication flows may differ between the web session and Android token clients, but both must resolve to the same authenticated user and authorization rules.

### Security requirements

- Passwords must never be returned in API responses.
- Android tokens must be stored using secure device storage.
- Protected endpoints must reject unauthenticated requests.
- Token revocation and logout must be supported.
- OTP verification must have expiration, rate limiting and abuse protection.
- Authentication failures must not reveal whether an account exists unnecessarily.

## 5. User and Shop Context

Kisan Khata follows this business rule:

**1 User = 1 Shop = 1 Role.**

A user belongs to exactly one shop and has one assigned role:

- Owner
- Manager
- Staff

The API must resolve the shop from the authenticated user's relationship.

Clients must not choose arbitrary shop IDs to access data.

A `shop_id` received from a client must never be treated as proof of authorization.

Every tenant-owned query and operation must be scoped to the authenticated user's shop.

There is no multi-shop switching or multiple-shop membership in the initial product architecture.

## 6. Authorization

Authorization must be enforced on the backend, not merely by hiding controls in the UI.

### Owner

- Full shop access.
- Manage Managers and Staff.
- Configure interest policies.
- Manage subscription and billing.
- Edit posted sales and payments.
- Access financial reports and exports.
- Manage shop settings.

### Manager

- Manage customers.
- Record sales and payments.
- View permitted reports.
- Perform permitted staff operations.
- Cannot edit posted financial transactions.
- Cannot change ownership, subscription or critical security settings.

### Staff

- View and add customers.
- Record credit sales.
- Record payments.
- View permitted customer and transaction information.
- View own work history.
- Cannot edit or delete posted financial transactions.
- Cannot access restricted settings or reports.

All API endpoints must enforce these role restrictions using Laravel Policies, Gates or an equivalent authorization layer.

## 7. Standard API Response Format

All API endpoints should follow a consistent response structure.

### Success response

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

### Validation error response

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "mobile": [
      "The mobile field is invalid."
    ]
  }
}
```

### Authorization error response

```json
{
  "success": false,
  "message": "You are not authorized to perform this action.",
  "errors": {}
}
```

The API must use appropriate HTTP status codes, including:

- `200` — Successful request.
- `201` — Resource created.
- `204` — Successful request without response body, where appropriate.
- `400` — Invalid request.
- `401` — Unauthenticated.
- `403` — Unauthorized.
- `404` — Resource not found or not accessible.
- `409` — Conflict or idempotency conflict.
- `422` — Validation failure.
- `429` — Rate limit exceeded.
- `500` — Internal server error.

The exact response contract must be documented and tested.

## 8. Pagination and Filtering

Collection endpoints must support pagination.

Example:

`GET /api/v1/customers?page=1&per_page=20`

Standard pagination metadata:

- Data.
- Current page.
- Per-page count.
- Total records.
- Last page.

The backend must enforce a maximum page size.

Relevant collection endpoints should support filters such as:

- Search query.
- From date.
- To date.
- Village.
- Customer.
- Transaction type.
- Status.

Only filters relevant to a particular endpoint should be exposed.

## 9. Customer Endpoints

Suggested endpoints:

### List customers

`GET /api/v1/customers`

Supports:

- Search by customer name or mobile.
- Village filter.
- Pagination.

### Create customer

`POST /api/v1/customers`

### View customer

`GET /api/v1/customers/{customer}`

### Update customer

`PUT /api/v1/customers/{customer}`

### Customer balance

`GET /api/v1/customers/{customer}/balance`

### Customer ledger

`GET /api/v1/customers/{customer}/ledger`

Customer fields must follow the approved product requirements:

- Name — required.
- Mobile — optional.
- Village — optional.
- Address — optional.
- Notes — optional.

The API must enforce tenant ownership and permissions for every customer operation.

## 10. Customer Language and Translation

The application supports Hindi and English.

Customer information may be entered in the user's selected language.

The backend must preserve:

- Original entered text.
- Original language.
- Hindi translation.
- English translation.

Translated values must be stored separately from the original value.

The original text must never be overwritten by an automatic translation.

Translation operations must support correction and editing of translated text without modifying the original entry.

The API should return language-specific values according to the requested or selected language, while retaining the original data internally.

Automatic translation must not block a successful customer transaction unnecessarily. Translation processing may be asynchronous where appropriate.

## 11. Sales Endpoints

Suggested endpoints:

- `GET /api/v1/sales`
- `POST /api/v1/sales`
- `GET /api/v1/sales/{sale}`
- `PUT /api/v1/sales/{sale}`

Sale information includes:

- Customer.
- Sale date.
- Amount.
- Description.
- Optional crop or season tag.

A product category or sale type is not mandatory.

### Financial transaction rules

- Posted sales must be protected against unauthorized changes.
- Only the Shop Owner can edit posted sales.
- Manager and Staff cannot edit or delete posted financial transactions.
- Owner corrections must create immutable audit history.
- A correction must trigger the required recalculation of affected customer balances and interest.
- Client-provided balances must never be accepted as authoritative accounting values.

Draft handling and transaction status must follow the approved business rules.

## 12. Payment Endpoints

Suggested endpoints:

- `GET /api/v1/payments`
- `POST /api/v1/payments`
- `GET /api/v1/payments/{payment}`
- `PUT /api/v1/payments/{payment}`

Payment information includes:

- Customer.
- Payment amount.
- Payment date.
- Optional payment method.
- Optional reference.
- Optional description.

### Consolidated payment model

Kisan Khata does not allocate payments to individual sale IDs.

The payment API must use the consolidated customer outstanding model:

1. Calculate eligible interest through the payment date.
2. Determine the customer's total payable balance.
3. Subtract the received payment from that balance.
4. Store the resulting consolidated outstanding.
5. Start the next calculation cycle for the carried-forward balance from the payment date.
6. Preserve the original dates of newer sales for their respective interest calculations.

If the payment exceeds the total payable balance, the excess amount must be recorded as customer advance.

Multiple payments on the same date must not cause duplicate interest for the same elapsed period.

### Payment response

After successful payment processing, the API should return:

- Payment amount.
- Previous payable balance.
- Interest calculated through the payment date.
- Resulting outstanding balance.
- Advance amount, if any.
- Transaction date.

Only the backend accounting engine may calculate these values.

### Correction rules

Only the Shop Owner may edit a posted payment.

Every correction must preserve the previous values in audit history and trigger the necessary balance and interest recalculation.

Manager and Staff cannot edit or delete posted payments.

## 13. Customer Ledger Endpoints

Suggested endpoints:

- `GET /api/v1/customers/{customer}/ledger`
- `GET /api/v1/customers/{customer}/balance`
- `GET /api/v1/customers/{customer}/ledger/print`

Optional query parameters:

- `from_date`
- `to_date`
- `page`
- `per_page`

The ledger must show the consolidated customer account, including:

- Sales.
- Payments.
- Interest entries.
- Settlements.
- Adjustments or corrections.
- Advance balance.
- Running balance.

The ledger must not depend on sale-level payment allocations.

All financial calculations and running balances must be generated by backend accounting services.

## 14. Interest Endpoints

Suggested endpoints:

- `GET /api/v1/interest/policies`
- `POST /api/v1/interest/policies`
- `GET /api/v1/interest/policies/{policy}`
- `PUT /api/v1/interest/policies/{policy}`
- `GET /api/v1/customers/{customer}/interest`

Interest policy configuration includes:

- Monthly interest rate.
- Grace period.
- Interest capitalization policy.
- Customer-specific rate, where configured.
- Two annual auto-settlement dates.

Only the Shop Owner may change interest policies.

Policy changes must be audited and must follow the approved effective-date rules.

### Accounting responsibilities

The API must not accept arbitrary interest amounts from clients as authoritative entries.

The backend must calculate interest according to the approved accounting rules.

Interest calculations occur on the approved business events:

1. Customer payment.
2. Configured annual auto settlement.

Payment and auto settlement on the same date must not calculate the same interest period twice.

The API must provide sufficient calculation details for transparent interest display, including applicable rate, date range, eligible days, calculation basis and amount.

## 15. Settlement Endpoints

Suggested endpoints:

- `GET /api/v1/settlements`
- `POST /api/v1/settlements`
- `GET /api/v1/settlements/{settlement}`

Settlement operations must support the approved business scenarios:

- Full settlement.
- Partial settlement.
- Carry-forward to the next crop season.

Settlement processing must be handled by backend accounting services.

The API must prevent duplicate settlement posting and maintain an audit trail.

## 16. Transaction Audit and Correction

The API must preserve audit history for sensitive financial operations.

Audit events include:

- Sale creation and correction.
- Payment creation and correction.
- Interest policy changes.
- Settlement operations.
- Customer changes affecting financial records.
- Other security-sensitive operations.

For Owner-authorized transaction corrections, the audit history must retain:

- Original values.
- Updated values.
- User who performed the correction.
- Timestamp.
- Reason for correction, where required.
- Related transaction identifier.

Audit history must not be editable through ordinary application endpoints.

## 17. File Upload API

Bill and receipt uploads must:

- Use authenticated endpoints.
- Validate file type and size.
- Store files privately.
- Associate files with tenant-owned records.
- Verify authorization before download.
- Avoid permanent public URLs for private financial documents.

Suggested endpoints:

- `POST /api/v1/files`
- `GET /api/v1/files/{file}`
- `DELETE /api/v1/files/{file}`

Deletion permissions must follow the applicable business and retention rules.

## 18. Idempotency

Financial endpoints must support idempotency to prevent duplicate transactions caused by network retries or repeated submissions.

Example request header:

`Idempotency-Key: unique-client-generated-value`

The server must:

1. Identify the key within the authenticated tenant and user context.
2. Verify that a repeated request has the same request payload.
3. Return the original result for a valid retry.
4. Reject reuse of the same key with a different payload.
5. Prevent duplicate financial posting.
6. Handle concurrent requests safely.

Idempotency is especially important for sales, payments and settlements.

## 19. API Security

The API must enforce:

- Authentication.
- Tenant isolation.
- Role-based authorization.
- Server-side validation.
- Rate limiting.
- Request size limits.
- Secure file handling.
- Safe error responses.
- Audit logging.
- Secure token handling.
- Protection against unauthorized resource access.

Sensitive fields must never be exposed unnecessarily.

All tenant-owned resources must be scoped to the authenticated user's shop.

## 20. API Resources and Data Exposure

Use Laravel API Resources or an equivalent transformation layer.

Do not expose Eloquent models directly without controlling serialized fields.

Never expose:

- Password hashes.
- Authentication tokens.
- Internal security fields.
- Unnecessary personal information.
- Unrestricted tenant metadata.
- Internal accounting implementation details that are not required by the client.

The API response must contain only the information needed by the authorized client.

## 21. Future Android Integration

The Android application will consume the same backend API.

### Android responsibilities

- Authentication UI.
- Customer search and forms.
- Sale entry.
- Payment entry.
- Ledger display.
- Reports.
- File uploads.
- Language selection.
- Display of backend-generated financial results.

### Backend responsibilities

- Authentication and authorization.
- Tenant isolation.
- Customer management.
- Interest calculation.
- Payment processing.
- Financial posting.
- Ledger generation.
- Transaction correction.
- Settlement processing.
- Audit history.
- Subscription enforcement.

The Android application must never independently calculate authoritative financial balances, interest or settlement amounts.

## 22. Offline Synchronization Readiness

Offline functionality is not part of the initial web release.

The backend architecture should remain suitable for a future Android offline synchronization feature.

Future offline support will require a separate design covering:

- Client-generated transaction identifiers.
- Idempotent synchronization.
- Conflict detection.
- Duplicate prevention.
- Server-authoritative financial posting.
- Sync status and retry handling.
- Conflict resolution for edited records.

Offline-created financial entries must not become authoritative until validated and accepted by the server.

## 23. API Compatibility and Documentation

Once an API contract is released:

- Avoid breaking existing clients.
- Add optional fields where practical.
- Use a new API version for breaking changes.
- Document deprecated endpoints.
- Maintain automated API tests.
- Keep API documentation synchronized with implementation.

OpenAPI/Swagger documentation may be used to describe endpoint contracts and request/response schemas.

## 24. API Testing

Automated API tests must cover:

- Authentication.
- OTP verification.
- Email/password login.
- User and shop context.
- Tenant isolation.
- Role permissions.
- Validation.
- Pagination and filters.
- Customer endpoints.
- Translation data handling.
- Sales endpoints.
- Payment endpoints.
- Consolidated ledger calculations.
- Interest calculations.
- Settlement processing.
- Idempotency.
- Transaction corrections and audit history.
- File uploads and downloads.
- Subscription restrictions.
- API compatibility.

Financial API tests must verify that the backend produces the correct authoritative results.

## 25. Acceptance Criteria

The API architecture is acceptable when:

1. API routes are versioned.
2. Authentication supports approved login methods.
3. One user resolves to exactly one shop and role.
4. Tenant isolation is enforced on every tenant-owned resource.
5. Role permissions are enforced on the backend.
6. Financial calculations are performed only by backend services.
7. Payments use the consolidated customer balance model.
8. No sale-level payment allocation API is required.
9. Owner corrections preserve immutable audit history.
10. Duplicate financial requests are prevented.
11. Responses follow a consistent contract.
12. Customer language and translation data are supported.
13. Android can use the same backend without duplicating accounting logic.
14. Automated API tests cover critical business and security rules.
