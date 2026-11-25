# Component Guidelines

## Buttons
- Primary: one clear primary action per context.
- Secondary: neutral outline or subtle surface.
- Danger: destructive actions only.
- Include visible focus and disabled states.
- Avoid icon-only buttons unless the action is universally clear and has an accessible name.

## Forms
- Persistent labels.
- Helpful validation messages next to the field.
- Preserve entered values after validation errors.
- Use correct input types and autocomplete attributes.
- Confirm destructive or irreversible operations.

## Cards
- Use cards to group related information.
- Avoid deeply nested cards.
- Keep spacing and heading hierarchy consistent.

## Tables and lists
- Align currency values consistently.
- Provide empty, loading and error states.
- Make row actions keyboard accessible.
- Follow [Table Guidelines](TABLE-GUIDELINES.md) for every production data table, including horizontal scrolling, first-column-only sticky behavior, equal outer content padding and unclipped row-action menus.
- Follow [Filter Guidelines](FILTER-GUIDELINES.md) for automatic listing filters, debounce, request cancellation and pagination state.
- Do not replace a required table with cards unless a documented and approved usability/accessibility exception exists.

## Alerts and badges
- Use semantic status colors.
- Pair color with text or an icon.
- Avoid excessive badges.

## Financial actions
- Distinguish sale, payment, outstanding and interest.
- Confirm the amount and customer before final submission.
- Never imply a transaction succeeded before the server confirms it.
## Right-side action drawer

The application-wide default for routine Create, Edit and record-level actions is the shared right-side action drawer. See [ADR-0012](../decisions/ADR-0012-application-wide-right-side-drawer-standard.md) and [ADR-0013](../decisions/ADR-0013-asynchronous-action-drawer-form-submission.md).

- Open only in response to an explicit user click; do not keep the drawer permanently open.
- On desktop, overlay the page from the right. Use approximately 480 CSS px as the default width; use a wider variant only when the form needs it.
- On mobile, use a full-screen or near-full-screen presentation.
- Keep listing rows free of embedded action forms and reason inputs. Use a compact, keyboard-accessible action trigger.
- Render only the selected task's form/content in the drawer.
- Keep the header and action footer visible; scroll the drawer body independently.
- All drawer forms MUST submit asynchronously through the shared Fetch API/Alpine.js mechanism; do not add jQuery solely for this purpose.
- Preserve form values and keep the drawer open on validation, authorization, server or network errors.
- Render the pending indicator as an overlay inside the existing submit control; preserve its dimensions and original content, and do not unmount the form or shift the drawer layout.
- Clear and replace prior server validation summaries and field errors before displaying the newest response on every retry; never append duplicate errors.
- On confirmed success, close the drawer and show the server-confirmed message in the shared floating toast, not an inline listing alert.
- Floating toasts must be globally reusable, accessible, responsive, manually dismissible and automatically dismissed after a reasonable interval.
- Display field-level Laravel JSON validation errors (HTTP 422) without reloading the page.
- Close only after an explicitly recognized server-confirmed success response. Prevent duplicate submissions while pending.
- On success, refresh/update the relevant listing and show server-confirmed feedback while preserving filters, pagination and scroll context.
- Keep CSRF, server-side validation, authorization, tenant isolation, audit and domain logic on the server.
- Use clear localized titles, accessible labels, visible focus and distinct destructive styling.
- Keep module-specific forms and business logic outside the shared drawer shell.

### Full-page exception

Use a dedicated page only when a multi-step workflow, complex table/calculation workspace, substantial information hierarchy or insufficient usable drawer space makes the drawer materially less usable. Field count alone is not decisive. Document the reason in the implementation/design review.


## Listing filters

Use [Filter Guidelines](FILTER-GUIDELINES.md). Routine filters apply automatically; text search uses a default 350–400 ms debounce. Keep query semantics, validation, authorization and tenant isolation server-side. Handle stale requests, loading/error states, pagination reset and URL state consistently.

## Production data tables

Use [Table Guidelines](TABLE-GUIDELINES.md). Every production data table has a horizontal scroll container, only the first column sticky on the left, all remaining columns including the last/action column scrolling normally, equal left/right outer content inset, a balanced short-result height and row-action overlays that are not clipped by ancestor overflow.
