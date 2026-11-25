# UI Implementation Guide

## Current frontend stack
- Tabler Core
- Tabler Icons Webfont
- Alpine.js
- Laravel Vite Plugin
- Vite

## Asset rules
- Keep CSS imports in resources/css/app.css.
- Keep JavaScript initialization in resources/js/app.js.
- Use Vite for production assets.
- Prefer self-hosted fonts.
- Avoid loading duplicate Bootstrap CSS or JavaScript.
- Do not reintroduce Tailwind.

## Blade architecture
Suggested future structure:

resources/views/
  layouts/
    app.blade.php
    guest.blade.php
  components/
    sidebar.blade.php
    topbar.blade.php
    page-header.blade.php
  dashboard/
    index.blade.php
  customers/
  transactions/
  ledger/

Create these incrementally when implementing real pages.

## Frontend conventions
- Blade for server-rendered markup.
- Alpine for small local interactions.
- Backend authorization is mandatory; hiding a button is not authorization.
- Validate all submitted data on the server.
- Escape untrusted text in Blade.
- Keep business calculations out of JavaScript.
- Keep financial calculations and persistence in Laravel services/domain logic.

## Quality gates
1. Vite production build succeeds.
2. No console errors.
3. Responsive viewport checks pass.
4. Keyboard navigation works.
5. Hindi text renders and wraps correctly.
6. No page-level horizontal overflow.
7. No business data is hardcoded in production views.
## Shared contextual action drawer

The application-wide drawer decisions are defined in [ADR-0012](../decisions/ADR-0012-application-wide-right-side-drawer-standard.md) and [ADR-0013](../decisions/ADR-0013-asynchronous-action-drawer-form-submission.md).

### Component responsibilities

- Implement one reusable Blade/Alpine drawer shell using the approved Tabler/Core, existing CSS tokens and Vite stack.
- Keep navigation sidebar state separate from contextual action drawer state.
- The shared shell owns overlay, open/close state, title/description slots, body scrolling, footer placement, focus/keyboard behaviour and responsive presentation.
- Module-specific Blade forms, routes, controllers, validation, services and authorization remain in their existing module boundaries.
- Do not place business rules, authoritative calculations or permission decisions in the shared JavaScript component.

### Interaction contract

- Open only after an explicit click on Add, Edit or a contextual record action.
- Render one selected task at a time.
- Desktop default width is approximately 480 CSS px; support a wider variant only when justified. Mobile uses full-screen or near-full-screen presentation.
- Keep header/footer visible and scroll the body independently.
- Preserve values on validation/server errors; close only after server-confirmed success.
- Prevent duplicate submission and preserve the underlying listing's scroll position.
- Keep backend authorization, tenant isolation, audit and lifecycle rules unchanged.

### Blade structure guidance

The exact component API and file placement should be selected during implementation after checking existing components and avoiding duplicate abstractions. A conceptual structure is:

```text
resources/views/components/ui/action-drawer.blade.php
resources/views/components/ui/drawer-header.blade.php
resources/views/components/ui/drawer-footer.blade.php
```

Do not create all suggested files automatically; implement the smallest reusable abstraction supported by the actual codebase.

### Full-page exception

Use a full-page workflow only when the drawer materially harms usability due to multi-step navigation, complex tables/calculations, required workspace width or independent sections that must be compared. Document the reason. Field count alone is not a decision rule.


## Asynchronous drawer form submission

All forms rendered in the contextual action drawer MUST use the shared asynchronous submission mechanism described by [ADR-0013](../decisions/ADR-0013-asynchronous-action-drawer-form-submission.md).

- Use the browser Fetch API, Alpine.js and FormData; do not add jQuery solely for drawer submission.
- Intercept only forms explicitly opted into the shared drawer contract. Do not globally intercept ordinary page forms.
- Send Accept: application/json and preserve same-origin session/CSRF behavior. FormData must preserve CSRF tokens, file controls and Laravel method spoofing.
- Define and document a predictable JSON success response for each operation. Handle Laravel validation JSON (HTTP 422) by mapping errors to the corresponding fields and retaining entered values.
- Keep the drawer open on validation, authorization, server, session or network errors. Never treat an ambiguous response as success.
- Show a pending indicator as an overlay inside the existing submit control. Preserve the button's dimensions and original content; do not unmount the form or shift drawer content.
- Prevent duplicate browser submissions and announce pending state accessibly.
- On each retry, clear/replace prior server-generated error summaries and field feedback before displaying the latest validation response. Preserve entered values and never append duplicate messages.
- On confirmed success, close the drawer and show the server-confirmed message through the shared floating toast region. Do not use an inline listing alert for routine drawer success.
- Refresh/update the relevant listing after success without discarding filters, pagination, sort or scroll context.
- Keep validation, authorization, tenant isolation, audit and domain logic in Laravel. Client-side visibility or disabled controls are not security or idempotency controls.
- Preserve existing non-AJAX behavior for non-drawer forms and non-drawer callers.

## Listing filters and asynchronous result refresh

Follow [Filter Guidelines](FILTER-GUIDELINES.md). Use server-side query services as the source of truth; apply text search with a default 350–400 ms debounce; apply discrete filters on change; cancel/ignore stale requests; reset pagination when filters change; synchronize URL state where practical; and preserve current results on request failure.

## Production data tables

Follow [Table Guidelines](TABLE-GUIDELINES.md). Every production data table must use a horizontally scrollable wrapper; only the first column is sticky at the left edge. The last and all other columns scroll normally. Preserve equal outer horizontal content insets, appropriate minimum height for short result sets and overlay positioning that prevents action menus from being clipped. Verify sticky stacking and focus visibility at supported viewports.


## Mandatory table pagination

Every production data table MUST use server-side pagination. Default to 10 or 20 rows and provide a page-size selector (10, 20, 50, 100), Previous/Next controls disabled at their boundaries, and a current-range/total-count indicator. Pass and validate `page` and `limit`/`pageSize` parameters through backend queries; never fetch all records for browser-side pagination. Preserve filters and sort state, enforce a server-side maximum of 100, and document any product-owner-approved exception. Follow [Table Guidelines](TABLE-GUIDELINES.md).
