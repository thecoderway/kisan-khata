# Kisan Khata — Security Architecture

**Document Version:** 2.0  
**Status:** Approved

---

## 1. Purpose

Kisan Khata stores customer information, shop information and financial records. Security must protect:

- Customer personal information.
- Shop financial information.
- Credit sales and payments.
- Customer outstanding balances and ledgers.
- Interest calculations and settlements.
- Uploaded documents and receipts.
- User accounts and authentication credentials.
- Subscription and billing information.

Security must be enforced at every application layer.

## 2. Security Principles

1. Least privilege.
2. Defense in depth.
3. Secure defaults.
4. Server-side authorization.
5. Strict tenant isolation.
6. Financial auditability.
7. Secure file handling.
8. Minimal exposure of sensitive information.
9. Secure handling of concurrent financial operations.
10. Traceability of sensitive administrative actions.

## 3. Authentication

Supported login methods:

- Mobile OTP.
- Email and password.

Requirements:

- Secure password hashing using Laravel-supported mechanisms.
- OTP expiration and single-use verification.
- Rate limiting for OTP requests and verification attempts.
- Email and mobile verification during registration, as applicable.
- Secure password reset.
- Session invalidation on logout.
- Session ID regeneration after authentication.
- Protection against brute-force attacks.
- No plaintext password or OTP storage.

Do not implement custom authentication cryptography.

Use established Laravel authentication mechanisms and vetted packages where required.

## 4. Session Security

Production configuration must include:

- HTTPS-only cookies.
- Secure cookie flag.
- HttpOnly cookie flag.
- Appropriate SameSite policy.
- Session ID regeneration after login.
- Session invalidation on logout.
- Reasonable session lifetime.
- Protection against session fixation.
- Secure session storage.

Cookie configuration must be compatible with the deployment architecture.

## 5. Authorization

Use Laravel Policies, Gates and application services.

Authorization must always be checked on the server.

Never rely on:

- Hidden buttons.
- Disabled form fields.
- Frontend role checks.
- URL obscurity.
- Unpredictable resource IDs.

Every protected resource must validate:

1. Authentication.
2. User's assigned shop.
3. Tenant ownership.
4. User role and permission.
5. Resource state.
6. Relevant business rules.

Tenant ownership and role authorization are separate checks. Both are mandatory.

## 6. Role and Permission Design

The initial release supports exactly three shop roles:

- Owner.
- Manager.
- Staff.

Platform administrators are separate from shop users.

There are no custom per-user permissions in the initial release.

### Owner

- Full access to the assigned shop.
- Manage shop settings.
- Manage subscription and billing.
- Create and manage Managers and Staff.
- Edit posted sales and payments under approved correction rules.
- Access all shop reports and financial records.
- Configure shop-level interest policies.

### Manager

- Manage customers.
- Manage operational sales and payments.
- View reports.
- Perform routine staff operations.
- Cannot change ownership, subscription or critical security settings.
- Cannot edit or delete posted financial transactions.

### Staff

- View and add customers.
- Record credit sales.
- Record payments.
- View own work and transaction history.
- Cannot edit or delete posted financial transactions.
- Has limited reports and settings access.

### Platform Administrator

Platform-level role, separate from shop roles.

Platform administrators may manage platform operations but must not receive unrestricted access to tenant financial information by default.

## 7. Tenant Isolation

Refer to `docs/05-multi-tenancy.md`.

The approved relationship is:

**1 User = 1 Shop = 1 Role**

Tenant context must be resolved from the authenticated user's assigned `shop_id`.

Never trust client-supplied `shop_id` as the authority.

Security checks must exist at:

- Authentication and middleware layer.
- Centralized tenant context.
- Query layer.
- Policy layer.
- Service layer.
- File access layer.
- API layer.
- Background job execution.

Every resource ID must be validated against the current tenant.

## 8. Input Validation

Use Laravel Form Requests or equivalent validation classes.

Validate:

- Required fields.
- Data types.
- Maximum lengths.
- Numeric ranges.
- Dates and date boundaries.
- Foreign key ownership.
- File MIME type and size.
- Allowed enum values.
- User role and operation permissions.

Validate again inside critical services wherever business invariants require it.

Frontend validation is for usability, not security.

Never assume that validated input automatically has valid tenant ownership.

## 9. Financial Operation Security

Financial operations require stronger controls.

Examples:

- Recording credit sales.
- Recording payments.
- Editing posted sales or payments by Owner.
- Interest calculations.
- Interest policy changes.
- Auto Settlement.
- Partial or full settlement.
- Carry-forward balance processing.

Requirements:

1. Validate user authorization.
2. Validate tenant and customer ownership.
3. Execute related database changes inside transactions.
4. Maintain audit history.
5. Apply idempotency where duplicate requests are possible.
6. Protect against concurrent balance updates.
7. Lock relevant financial records where required.
8. Recalculate affected balances when an authorized correction requires it.
9. Prevent arbitrary direct ledger modification.
10. Require explicit confirmation for sensitive operations.

### Consolidated Payment Model

Payments are applied against the customer's consolidated payable balance.

The application must not permit arbitrary sale-level payment allocation.

A payment operation must:

- Calculate eligible outstanding and interest through the payment date.
- Subtract the received payment from total payable.
- Preserve any excess amount as customer advance.
- Record the resulting balance and calculation reference.
- Prevent duplicate processing of the same payment request.

Multiple payments on the same date must not cause interest to be calculated repeatedly for the same elapsed period.

### Transaction Corrections

Only the Shop Owner may edit posted financial transactions under the approved business rules.

Requirements:

- Preserve original values.
- Record updated values.
- Record who made the change and when.
- Record the reason for correction where required.
- Maintain immutable audit history.
- Reconcile affected balances and interest calculations.
- Prevent unauthorized users from editing or deleting posted entries.

The audit history must never be overwritten or deleted through ordinary application operations.

## 10. File Upload Security

Bills, receipts and documents may contain private customer information.

Requirements:

- Use private storage.
- Validate MIME type and file extension.
- Validate file size.
- Generate random storage names.
- Never trust the original filename.
- Reject executable content.
- Prevent path traversal.
- Authorize every download.
- Validate the tenant ownership of the related database record.
- Apply malware scanning when operationally available.

Possible initial formats:

- JPEG.
- PNG.
- WebP.
- PDF.

Follow the private tenant storage structure defined in `docs/05-multi-tenancy.md`.

Never store sensitive financial attachments in an unrestricted public directory.

## 11. Database Security

Requirements:

- Use parameterized queries.
- Prefer Eloquent and query builder parameter binding.
- Avoid unsafe raw SQL.
- Use least-privilege database credentials.
- Separate development and production credentials.
- Protect database backups.
- Restrict database network access.
- Use encrypted database connections where supported.
- Apply migrations through controlled deployment procedures.
- Enforce tenant-aware relational integrity where practical.

Never expose database credentials in source control.

## 12. API Security

Future APIs must:

- Use secure authentication.
- Validate authorization on every request.
- Resolve tenant context from the authenticated user.
- Validate ownership of all resource IDs.
- Apply rate limits to sensitive endpoints.
- Validate all input.
- Use versioned routes.
- Return only required fields.
- Avoid exposing internal exception traces.
- Prevent duplicate financial submissions.
- Apply the same role and business rules as the web application.

API authentication must never bypass shop membership or tenant ownership checks.

Future Android applications must follow the same security rules.

## 13. CSRF and XSS

Use Laravel CSRF protection for browser forms.

Escape user-generated output by default.

Do not render customer names, descriptions, notes or other user-generated content as trusted HTML.

Sanitize any feature that intentionally accepts rich text.

Use appropriate security headers in production.

## 14. SQL Injection

Use Eloquent and query builder parameter binding.

Never concatenate untrusted input into raw SQL.

Any raw SQL must be parameterized and reviewed.

## 15. Audit Logging

Maintain protected audit records for important security and business events.

Examples:

- Login and authentication security events.
- OTP verification failures where appropriate.
- User account creation and deactivation.
- Role changes.
- Shop ownership or security setting changes.
- Customer changes.
- Sale creation and correction.
- Payment creation and correction.
- Interest policy changes.
- Interest calculation and settlement events.
- Subscription changes.
- Sensitive file access where justified.
- Platform administrator actions.

Audit records should capture, where applicable:

- Shop ID.
- Actor user ID.
- Action type.
- Affected record type and ID.
- Previous values.
- New values.
- Timestamp.
- Reason or reference.
- Request or correlation identifier.

Audit history must be append-only from the application's normal operation.

Ordinary shop users must not be able to alter or delete audit records.

Never store passwords, OTPs, authentication tokens or unnecessary sensitive information in logs.

## 16. Rate Limiting

Apply configurable rate limits to:

- Login.
- OTP generation.
- OTP verification.
- Password reset.
- Public registration.
- Customer search APIs.
- Financial posting APIs.
- File access endpoints.
- Sensitive administrative endpoints.

Monitor repeated failures and suspicious activity.

Rate limits should consider both user and IP-based abuse where appropriate.

## 17. Error Handling

Production responses must not expose:

- Stack traces.
- SQL queries.
- Internal filesystem paths.
- Secrets.
- Authentication tokens.
- Unnecessary personal information.
- Internal tenant data.

Log sufficient diagnostic information securely.

Use generic client-facing error responses for unexpected failures.

## 18. Secrets Management

Store secrets in environment variables or an approved secret manager.

Never commit:

- Database passwords.
- API keys.
- Payment gateway secrets.
- SMTP passwords.
- Application encryption keys.
- OTP provider credentials.
- Access tokens.

Do not reuse production secrets in development.

Rotate credentials when compromise is suspected or access changes require it.

## 19. Backup and Recovery

Establish:

- Daily automated database backups.
- Encrypted off-site backups.
- Defined backup retention policy.
- Restricted backup access.
- Recovery procedures.
- Periodic restore tests.
- Defined Recovery Point Objective (RPO).
- Defined Recovery Time Objective (RTO).

Backup retention must be aligned with business, legal and accounting requirements.

A backup that has never been restored is not a verified recovery plan.

## 20. Background Job Security

Tenant-specific background jobs must carry explicit `shop_id`.

Requirements:

- Validate shop existence and status.
- Establish tenant context explicitly.
- Validate relevant record ownership.
- Clear tenant context after execution.
- Prevent tenant context leakage between jobs.
- Make financial jobs idempotent where required.
- Protect scheduled interest and settlement operations against duplicate execution.

Never assume a background worker has the original user's authenticated session.

## 21. Production Security Checklist

Before production:

- HTTPS enabled.
- Debug mode disabled.
- Secrets protected.
- Database access restricted.
- File storage private.
- Authentication rate limiting enabled.
- OTP security verified.
- Session security configured.
- Authorization tests passing.
- Tenant isolation tests passing.
- Financial operation tests passing.
- Audit logging enabled.
- Backup configured.
- Restore tested.
- Logs protected.
- Dependencies reviewed.
- Production error responses sanitized.

## 22. Security Review

Every significant feature must be reviewed for:

- Broken access control.
- Cross-tenant access.
- Privilege escalation.
- Injection vulnerabilities.
- Sensitive data exposure.
- Unsafe file handling.
- Race conditions.
- Duplicate financial requests.
- Missing audit trail.
- Unauthorized financial corrections.
- Background job tenant leakage.
- Insecure API behavior.

Security review must be part of feature completion.

## 23. Acceptance Criteria

Security is not complete merely because login works.

The application must demonstrate:

- Secure authentication.
- Server-side role authorization.
- Strict tenant isolation.
- Protected financial operations.
- Immutable audit history.
- Secure file access.
- Safe API behavior.
- Protected secrets and database access.
- Background job tenant isolation.
- Verified backup and recovery procedures.
- Automated security and cross-tenant tests.

All critical security tests must pass before production release.
