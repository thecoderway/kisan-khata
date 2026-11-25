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

The application-wide drawer decision is defined in [ADR-0012](../decisions/ADR-0012-application-wide-right-side-drawer-standard.md).

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
