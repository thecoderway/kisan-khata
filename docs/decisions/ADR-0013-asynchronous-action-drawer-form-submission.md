# ADR-0013: Asynchronous Action Drawer Form Submission

- **Status:** ACCEPTED
- **Date:** 2026-11-25
- **Decision owner:** Product Owner
- **Scope:** All current and future authenticated web modules that use the shared action drawer

## Context

ADR-0012 established the application-wide right-side action drawer and requires it to remain open when validation or server errors occur, preserve entered values, and close only after server-confirmed success.

The initial drawer implementation used ordinary browser form submissions. A redirect after validation failure reloads the page and requires the drawer context to be restored. This interrupts the user's workflow and creates avoidable UI state complexity.

The approved frontend foundation is Laravel Blade, Tabler Core, Alpine.js, Vite and the existing CSS/JavaScript assets. jQuery is not currently part of the approved frontend stack.

## Decision

All forms submitted from the shared contextual action drawer MUST use asynchronous submission. Use the browser Fetch API with the existing Alpine.js and Blade architecture unless a future superseding ADR approves a different transport or framework.

This decision supplements ADR-0012. It does not require ordinary full-page forms outside the action drawer to use AJAX.

## Interaction contract

1. Submitting a drawer form MUST NOT cause a full-page navigation or reload.
2. The drawer MUST remain open while the request is pending and after validation, authorization, server or network failures.
3. User-entered values MUST remain available after an unsuccessful request. Do not replace the form with a blank form on validation failure.
4. The submit control MUST expose a clear pending state and prevent duplicate submissions while the request is in flight. The pending indicator MUST be overlaid within the existing submit control without replacing its original content, changing its dimensions, unmounting the form, or shifting surrounding layout. Restore the original control presentation after the request settles.
5. A drawer MUST close only after the server returns a response that the client recognizes as successful for the requested operation.
6. After confirmed success, close the drawer, update or refresh the relevant listing/context, and show the server-confirmed message in the shared floating toast region. Do not use an inline page alert for routine drawer success. Do not optimistically claim that a write succeeded.
7. Network failures and ambiguous responses MUST NOT trigger automatic blind resubmission of a write request. Explain the uncertainty and allow the user to verify the current record state before retrying.
8. A request that returns validation errors MUST display field-level errors beside the relevant controls and an accessible summary when appropriate. On every new submission attempt, remove the previous server-generated summary and field errors before presenting the latest response; repeated validation failures MUST replace prior errors rather than append duplicate messages. Preserve all user-entered values.
9. Non-validation failures MUST display a useful, localized message without exposing stack traces, SQL details, secrets or internal exception content.
10. Preserve the user's listing context, active filters, pagination state and scroll position when the drawer closes or its result is refreshed.

## Request and response contract

- Use Fetch API and FormData so standard form controls, file inputs where supported, Laravel method spoofing through the _method field and CSRF form tokens can be submitted without manually rebuilding the payload.
- Send the appropriate Accept: application/json header and retain the application's normal same-origin session and CSRF protections.
- Keep server-side validation authoritative. A validation response should use Laravel's conventional JSON validation response (HTTP 422) with field errors that the client can map to controls.
- Successful responses MUST have an explicit, documented JSON shape for the operation and enough information for the client to update or refresh the relevant UI. Do not infer success from an HTML redirect or from a generic HTTP 200 response whose meaning is unclear.
- Handle authentication/session expiry, authorization denial, CSRF/session expiry, rate limiting, validation failure, server failure and network failure as distinct user-facing states where the response allows it.
- Controllers and routes MUST preserve their existing authorization, tenant scoping, policies, validation, audit logging, lifecycle rules and business transaction boundaries.
- Existing non-drawer callers and ordinary browser forms MUST retain their intended response behavior. Add JSON negotiation without accidentally converting unrelated web navigation into JSON-only behavior.

## Shared component responsibilities

The shared drawer submission mechanism owns transport state and presentation only:

- submit interception for opted-in drawer forms;
- stable, non-layout-shifting pending indicator within the submit control;
- replacement/cleanup of prior server validation errors on each attempt;
- shared floating toast feedback after confirmed success;
- pending/disabled state and duplicate-submit prevention;
- field error rendering and clearing;
- general success/error feedback;
- response interpretation and the confirmed-success close/update callback.

It MUST NOT own domain rules, financial calculations, authorization decisions, tenant selection, audit semantics or authoritative data state. Those remain on the server in the relevant module.

Use the existing Alpine.js and Fetch API foundation. Do not add jQuery or another dependency solely for drawer submission. Do not create separate, inconsistent AJAX implementations in individual modules when the shared mechanism can support the use case.

## Accessibility and resilience

- Associate field errors with their inputs and expose invalid state accessibly.
- Pending indicators and floating toasts MUST expose appropriate accessible status/live-region semantics.
- Floating toasts MUST be keyboard dismissible, readable over the page, responsive on mobile and automatically dismiss after a reasonable interval; users must also be able to dismiss them manually.
- Announce pending and completion/error feedback through appropriate live regions.
- Keep focus behavior consistent with the modal drawer contract in ADR-0012.
- Do not rely on color alone to communicate errors or success.
- Ensure the form remains usable when JavaScript is unavailable only where a deliberate progressive-enhancement fallback is provided; otherwise, the application must fail clearly rather than silently discard a submission.
- Test slow responses, validation errors, session expiry, server errors, network interruption and repeated activation.

## Security and data integrity

This is a presentation and transport decision, not a relaxation of server-side controls.

- CSRF protection remains mandatory.
- Authorization and tenant isolation MUST be enforced on every request.
- Server-side validation remains authoritative.
- Audit records and lifecycle protections remain unchanged.
- Financial operations MUST retain their separately approved idempotency, transaction and concurrency safeguards; disabling a button in the browser is not a substitute for backend protections.
- Never trust client-supplied success state, record identifiers, role information or calculated financial values.

## Consequences

### Positive
- Validation errors remain in the active drawer without a page reload.
- Users retain form values and listing context.
- Drawer forms share a consistent submission and feedback model.
- Future modules can reuse the same interaction mechanism without adding a frontend dependency.

### Trade-offs
- Controllers must negotiate and return well-defined JSON responses for drawer requests.
- Shared JavaScript must handle transport, error mapping, accessibility and lifecycle callbacks consistently.
- Integration tests must cover both JSON drawer requests and existing non-AJAX browser behavior.

## Alternatives considered

- **Add jQuery:** not selected because it is not currently part of the approved frontend stack and Fetch API covers the required behavior.
- **Keep ordinary redirect submissions:** rejected for drawer forms because page reloads interrupt the drawer workflow.
- **Implement custom AJAX in each module:** rejected because it duplicates behavior and creates inconsistent UX.
- **Move domain logic into JavaScript:** rejected because the server remains authoritative for validation, authorization and business state.

## Related decisions and documents

- [ADR-0012 — Application-Wide Right-Side Drawer Standard](ADR-0012-application-wide-right-side-drawer-standard.md)
- [UI Implementation Guide](../ui/UI-IMPLEMENTATION-GUIDE.md)
- [Component Guidelines](../ui/COMPONENT-GUIDELINES.md)
- [Filter Guidelines](../ui/FILTER-GUIDELINES.md)
- [Table Guidelines](../ui/TABLE-GUIDELINES.md)
- [Responsive Guidelines](../ui/RESPONSIVE-GUIDELINES.md)

## Implementation refinement — 2026-11-25

The Product Owner approved the following refinements to this ADR:

- Pending UI is a positioned overlay inside the existing submit button. Do not replace button contents or resize the button; do not unmount or replace the form while the request is pending.
- Each retry clears all prior server-generated error summaries, field feedback and invalid-state associations before rendering the newest response. Never append duplicate error blocks across repeated 422 responses.
- A confirmed success closes the drawer and emits a shared floating toast using the server's message. The toast is independent of any particular module and is mounted once in the authenticated application layout.
- The shared toast supports success/error semantics, a live region, manual dismissal and timed dismissal. Routine successful drawer submissions must not use an inline alert inside the listing.

These refinements are mandatory for all future drawer forms and are part of the original accepted asynchronous submission contract.

## Reconsideration

Any change to the required asynchronous submission behavior, transport standard or shared component boundary requires a superseding ADR and Product Owner approval.
