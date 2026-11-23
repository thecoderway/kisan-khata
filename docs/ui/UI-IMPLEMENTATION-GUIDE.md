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
