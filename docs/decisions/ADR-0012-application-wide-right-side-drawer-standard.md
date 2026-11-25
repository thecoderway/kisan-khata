# ADR-0012: Application-Wide Right-Side Drawer Standard

- **Status:** ACCEPTED
- **Date:** 2026-11-25
- **Decision owner:** Product Owner
- **Scope:** All authenticated application modules and future web modules

## Context

Kisan Khata needs a consistent interaction pattern for record-level actions and routine forms. Inline action forms inside table rows make listings crowded, widen tables, and mix record data with administrative workflows. Separate full-page create/edit screens for small tasks also interrupt the user's context.

The existing UI foundation uses Laravel Blade, Tabler Core, Alpine.js and Vite. The existing accessibility guidelines already require keyboard-accessible dialogs/drawers, focus management and Escape support.

## Decision

Adopt a shared **Right-Side Action Drawer** as the default presentation pattern across the application.

1. The drawer opens only after an explicit user action, such as Add, Edit or a record action click. It is not permanently open.
2. On desktop, it enters from the right and overlays the current page. It must not reflow or permanently resize the underlying page.
3. Routine create, edit, view-context actions, lifecycle actions and their short forms belong in the drawer by default.
4. Listing pages remain focused on search, filters, pagination, record information, status and a compact contextual action trigger. Do not render action forms and reason inputs inline in each table row.
5. A single shared drawer shell provides the common presentation and interaction behaviour. Module-specific forms and business rules remain owned by their respective modules.
6. On mobile, the same interaction becomes a full-screen or near-full-screen drawer to preserve usable form space.
7. A dedicated full-page form/workflow is an exception, allowed when its complexity or information architecture makes the drawer materially less usable. The exception must be justified in the relevant implementation/design review.

## Default width and layout guidance

- Desktop default width: approximately 480 CSS px.
- A wider variant may be used when the form genuinely needs it; keep within the available viewport and avoid horizontal overflow.
- The drawer occupies the viewport height.
- Header and action footer remain visible (sticky/fixed within the drawer); the body scrolls independently.
- The underlying page is visually subdued by a backdrop and cannot receive pointer or keyboard interaction while the modal drawer is open.
- Preserve the underlying page's scroll position and listing context when closing the drawer.

The width is a design default, not a hard limit. Validate it against Hindi/English labels, actual form controls and supported desktop viewport sizes.

## Drawer content and interaction

- Use a clear localized title and, where applicable, the selected record's name or identifying context.
- Render only the selected task's content; do not display every possible action form simultaneously.
- Provide a visible close control and a clear Cancel action where appropriate.
- Place primary and secondary form actions in a consistent footer. Keep destructive actions visually distinct.
- Prevent duplicate submissions while a request is pending.
- Keep the drawer open on validation or server errors and preserve submitted values.
- Close after successful completion only when the server confirms success; then refresh/update the relevant listing state and show feedback.
- For destructive or lifecycle actions, retain existing confirmation, reason-capture, audit and authorization requirements.

## Full-page exception criteria

Use a dedicated page when one or more material constraints make the drawer unsuitable, for example:

- a multi-step workflow with meaningful navigation/progress;
- a large form with several independent sections that must be compared or edited together;
- a complex editable table, dense calculations or a workspace needing substantial width;
- a task where the drawer would make the information hierarchy, validation or keyboard operation confusing.

Field count alone does not decide placement. The developer must consider workflow complexity, content width, viewport, user task and accessibility. Record the reason for a full-page exception in the implementation/design review. When uncertain, prefer the drawer and validate usability.

## Shared component boundaries

The shared drawer is a presentation and interaction component. It must not own domain logic.

- Use the approved frontend stack: Blade, Tabler Core/Icons, Alpine.js, existing CSS tokens and Vite.
- Keep module forms, routes, controllers, services, validation and policies in their existing domain boundaries.
- Do not move business calculations or authoritative state into JavaScript.
- Do not weaken server-side authorization, tenant isolation, audit logging, lifecycle policy or financial safeguards.
- Reuse existing routes and server-side form handling where practical; asynchronous submission is not required by this ADR.
- Keep navigation sidebar behaviour separate from contextual action drawer behaviour.

## Accessibility and responsive requirements

- Implement modal-dialog semantics and an accessible name/title.
- Move focus into the drawer on open, keep keyboard focus within it while modal, and return focus to the triggering control on close.
- Support Escape and a visible close button, except where an in-progress/critical operation requires a deliberate confirmation before dismissal.
- Lock background interaction and scrolling while open; restore prior state on close.
- Provide visible focus, associated labels and field-level validation feedback.
- Respect reduced-motion preferences.
- Ensure touch targets are at least 44 × 44 CSS px where practical.
- At mobile widths, use a full-screen/near-full-screen layout with independently scrolling content and reachable actions.
- Test Hindi and English content, long labels and browser zoom.

## Consequences

### Positive
- Cleaner, narrower and more readable listing pages.
- Consistent location for routine create/edit/lifecycle actions.
- Users retain context and can return to the same list position.
- Shared component reduces duplicated interaction code and visual drift.
- Future modules inherit a clear default.

### Trade-offs
- Requires a reusable component and migration of existing inline/page-based flows.
- Drawer state, focus management, scroll locking and error handling need careful implementation.
- Some complex workflows will remain full-page by design.

## Alternatives considered

- **Keep inline row forms:** rejected because they clutter tables and scale poorly as actions grow.
- **Use a separate full page for every action:** rejected as the default because it interrupts routine tasks and creates inconsistent navigation.
- **Keep navigation sidebar permanently open as the action surface:** rejected because navigation and record-context actions have different responsibilities.
- **Use independent modal implementations in each module:** rejected because it duplicates behaviour and leads to inconsistent accessibility and UX.

## Security and data-integrity impact

This is a UI presentation decision only. It does not authorize changes to backend permissions, tenant scoping, lifecycle rules, audit semantics, financial posting, database schema or API contracts. Backend authorization and validation remain mandatory regardless of what controls are visible.

## Related documents

- [UI/UX Guidelines](../07-ui-ux-guidelines.md)
- [Component Guidelines](../ui/COMPONENT-GUIDELINES.md)
- [Page Layouts](../ui/PAGE-LAYOUTS.md)
- [Responsive Guidelines](../ui/RESPONSIVE-GUIDELINES.md)
- [Accessibility](../ui/ACCESSIBILITY.md)
- [UI Implementation Guide](../ui/UI-IMPLEMENTATION-GUIDE.md)

## Reconsideration

Revisit this decision only if usability testing, accessibility findings or a materially different platform interaction model demonstrates that the shared drawer default is unsuitable. Any change to this application-wide standard requires a superseding ADR and Product Owner approval.
