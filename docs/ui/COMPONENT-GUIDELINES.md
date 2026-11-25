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
- Provide mobile card alternatives where appropriate.

## Alerts and badges
- Use semantic status colors.
- Pair color with text or an icon.
- Avoid excessive badges.

## Financial actions
- Distinguish sale, payment, outstanding and interest.
- Confirm the amount and customer before final submission.
- Never imply a transaction succeeded before the server confirms it.
## Right-side action drawer

The application-wide default for routine Create, Edit and record-level actions is the shared right-side action drawer. See [ADR-0012](../decisions/ADR-0012-application-wide-right-side-drawer-standard.md).

- Open only in response to an explicit user click; do not keep the drawer permanently open.
- On desktop, overlay the page from the right. Use approximately 480 CSS px as the default width; use a wider variant only when the form needs it.
- On mobile, use a full-screen or near-full-screen presentation.
- Keep listing rows free of embedded action forms and reason inputs. Use a compact, keyboard-accessible action trigger.
- Render only the selected task's form/content in the drawer.
- Keep the header and action footer visible; scroll the drawer body independently.
- Preserve form values and keep the drawer open on validation or server errors.
- Close only after server-confirmed success. Prevent duplicate submissions while pending.
- Use clear localized titles, accessible labels, visible focus and distinct destructive styling.
- Keep module-specific forms and business logic outside the shared drawer shell.

### Full-page exception

Use a dedicated page only when a multi-step workflow, complex table/calculation workspace, substantial information hierarchy or insufficient usable drawer space makes the drawer materially less usable. Field count alone is not decisive. Document the reason in the implementation/design review.
