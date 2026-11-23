
# Kisan Khata — Security Architecture

**Document Version:** 1.0

---

## 1. Purpose

Kisan Khata stores customer information and financial records.

Security must protect:

- Customer personal information
- Shop financial information
- Credit sales
- Payments
- Interest calculations
- Uploaded bills
- User accounts
- Subscription information

Security must be considered at every application layer.

## 2. Security Principles

1. Least privilege.
2. Defense in depth.
3. Secure defaults.
4. Explicit authorization.
5. Tenant isolation.
6. Financial auditability.
7. Secure file handling.
8. Minimal exposure of sensitive data.

## 3. Authentication

Use Laravel's established authentication mechanisms.

Requirements:

- Secure password hashing.
- Login validation.
- Password reset.
- Session security.
- Logout and session invalidation.
- Optional email or mobile verification.
- Rate limiting for authentication attempts.

Never store plaintext passwords.

Do not create custom authentication cryptography.

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

Cookie configuration must be compatible with the deployment architecture.

## 5. Authorization

Use Laravel Policies and Gates.

Authorization must be checked on the server.

Do not rely on:

- Hidden buttons.
- Disabled form fields.
- Frontend role checks.
- URL obscurity.
- Unpredictable IDs.

Every protected resource must verify:

1. Authentication.
2. Shop membership.
3. Tenant ownership.
4. User permission.
5. Resource state where applicable.

## 6. Role and Permission Design

Suggested roles:

- Platform Administrator
- Shop Owner
- Shop Manager
- Accountant
- Sales Employee
- Read-only User

Permissions should be independently configurable.

Suggested permissions:

- customers.view
- customers.create
- customers.update
- sales.view
- sales.create
- sales.post
- sales.reverse
- payments.view
- payments.create
- payments.post
- payments.reverse
- ledger.view
- ledger.print
- interest.view
- interest.configure
- settlement.view
- settlement.create
- settlement.post
- reports.view
- employees.manage
- subscription.manage

Sensitive financial permissions must be restricted.

## 7. Tenant Isolation

Refer to docs/05-multi-tenancy.md.

Security checks must exist at:

- Middleware or tenant context layer.
- Query layer.
- Policy layer.
- Service layer.
- File access layer.

Never trust shop_id from client requests.

## 8. Input Validation

Use Laravel Form Requests or equivalent validation classes.

Validate:

- Required fields.
- Data types.
- Maximum lengths.
- Numeric ranges.
- Dates.
- Foreign key ownership.
- File type.
- File size.
- Allowed enum values.

Validate again inside critical services where business invariants require it.

Do not treat frontend validation as security.

## 9. Financial Operation Security

Financial actions require stronger protection.

Examples:

- Posting credit sales.
- Posting payments.
- Reversing payments.
- Changing interest policies.
- Posting interest.
- Creating settlements.
- Reversing settlements.

Requirements:

- Authorization.
- Database transaction.
- Ownership validation.
- Audit record.
- Idempotency where required.
- Concurrency protection.
- Explicit confirmation for sensitive user actions.

Never expose a general-purpose endpoint that can arbitrarily modify ledger entries.

## 10. File Upload Security

Bills and receipts may contain private customer information.

Requirements:

- Use private storage.
- Validate MIME type and file extension.
- Validate file size.
- Generate random storage names.
- Do not trust the original filename.
- Reject executable content.
- Prevent path traversal.
- Authorize every download.
- Restrict supported formats.
- Apply malware scanning when operationally available.

Possible initial formats:

- JPEG
- PNG
- WebP
- PDF

Never store sensitive financial attachments in an unrestricted public directory.

## 11. Database Security

Requirements:

- Use parameterized queries.
- Avoid unsafe raw SQL.
- Use least-privilege database credentials.
- Separate development and production credentials.
- Protect backups.
- Restrict database network access.
- Use encrypted connections where supported.
- Apply migrations through controlled deployment procedures.

Do not expose database credentials in source control.

## 12. API Security

Future APIs must:

- Use secure authentication.
- Validate authorization on every request.
- Apply tenant isolation.
- Rate-limit sensitive endpoints.
- Validate input.
- Use versioned routes.
- Return only required fields.
- Avoid exposing internal exception traces.

API authentication must not bypass shop membership checks.

## 13. CSRF and XSS

Use Laravel CSRF protection for browser forms.

Escape user-generated output by default.

Do not render customer descriptions or notes as trusted HTML.

Sanitize any feature that intentionally accepts rich text.

## 14. SQL Injection

Use Eloquent and query builder parameter binding.

Do not concatenate untrusted input into raw SQL.

Any raw SQL must be reviewed and parameterized.

## 15. Audit Logging

Record important events:

- Login security events.
- Shop membership changes.
- Permission changes.
- Customer changes.
- Financial posting.
- Financial reversal.
- Interest policy changes.
- Settlement posting.
- Subscription changes.
- File access events where justified.

Audit records must be protected from ordinary users.

Avoid storing passwords, tokens or unnecessary sensitive information.

## 16. Rate Limiting

Apply rate limits to:

- Login.
- Password reset.
- OTP verification if implemented.
- Customer search APIs.
- Financial posting APIs.
- File access endpoints.
- Public registration.

Limits should be configurable and monitored.

## 17. Error Handling

Production responses must not expose:

- Stack traces.
- SQL queries.
- Internal filesystem paths.
- Secrets.
- Authentication tokens.
- Unnecessary personal information.

Log sufficient diagnostic information securely.

## 18. Secrets Management

Store secrets in environment variables or an approved secret manager.

Never commit:

- Database passwords.
- API keys.
- Payment gateway secrets.
- SMTP passwords.
- Application encryption keys.

Do not reuse production secrets in development.

## 19. Backup and Recovery

Establish:

- Automated database backups.
- Backup retention policy.
- Secure backup storage.
- Recovery procedure.
- Periodic restore tests.
- Defined recovery point objective.
- Defined recovery time objective.

A backup that has never been restored is not a verified recovery plan.

## 20. Production Security Checklist

Before production:

- HTTPS enabled.
- Debug disabled.
- Secrets protected.
- Database access restricted.
- File storage private.
- Authentication rate limiting enabled.
- Authorization tests passing.
- Tenant isolation tests passing.
- Financial operation tests passing.
- Backup configured.
- Restore tested.
- Logs protected.
- Dependencies reviewed.

## 21. Security Review

Every significant feature must be reviewed for:

- Broken access control.
- Cross-tenant access.
- Injection.
- Sensitive data exposure.
- Unsafe file handling.
- Race conditions.
- Duplicate financial requests.
- Missing audit trail.

## 22. Acceptance Criteria

Security is not complete merely because login works.

The application must demonstrate authorization, tenant isolation, financial integrity, protected file access and tested recovery procedures.
