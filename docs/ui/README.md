# Kisan Khata UI Foundation

Status: Complete — design system, frontend foundation, and reviewed UI prototypes.

## Approved stack
- Laravel Blade
- Tabler Core (Bootstrap-based)
- Tabler Icons Webfont
- Alpine.js
- Vite
- Custom CSS design tokens
- Inter and Noto Sans Devanagari typography guidelines

## Approved design system
- Primary green: #166534
- Secondary green: #15803D
- Light green: #DCFCE7
- Background: #F8FAFC
- Surface: #FFFFFF
- Text: #0F172A
- Muted text: #64748B

## Design principles
- Mobile-first, responsive layouts.
- Hindi and English support.
- Preserve user-entered text.
- Consistent spacing, typography and components.
- Accessible keyboard navigation and visible focus.
- No horizontal page overflow.
- Financial values must remain clear and unambiguous.
- UI prototypes use illustrative data only.

## Documents
- DESIGN-SYSTEM.md
- COLOR-PALETTE.md
- TYPOGRAPHY.md
- RESPONSIVE-GUIDELINES.md
- TABLE-GUIDELINES.md
- FILTER-GUIDELINES.md
- COMPONENT-GUIDELINES.md
- PAGE-LAYOUTS.md
- ACCESSIBILITY.md
- UI-IMPLEMENTATION-GUIDE.md

## Reviewed prototypes
- prototypes/dashboard.html
- prototypes/login.html
- prototypes/customers.html
- prototypes/sale-payment.html
- prototypes/customer-ledger.html
- prototypes/prototype.css — shared prototype styles

## Validation
- All five prototype pages returned HTTP 200.
- Tabler CSS and Icons assets returned HTTP 200.
- HTML structure checks passed.
- Vite production build passed.
- Laravel test suite passed.
- Git whitespace check passed.

## Scope
This phase establishes the frontend dependency foundation, design guidelines, and standalone UI prototypes. The prototypes are not connected to Laravel routes, authentication, business logic, or MySQL. Integration into the actual application will happen in subsequent phases.
## Application-wide interaction standard

The approved default for routine Create, Edit and record-level actions is the right-side overlay action drawer. It opens on explicit user click; it is not permanently visible. Listing pages remain data-focused and must not contain repeated inline action forms. Mobile uses a full-screen or near-full-screen presentation. Complex workflows may use a dedicated page only with a documented usability justification.

See:
- [ADR-0012 — Application-Wide Right-Side Drawer Standard](../decisions/ADR-0012-application-wide-right-side-drawer-standard.md)
- [Component Guidelines](COMPONENT-GUIDELINES.md)
- [Page Layouts](PAGE-LAYOUTS.md)
- [Responsive Guidelines](RESPONSIVE-GUIDELINES.md)
- [Accessibility](ACCESSIBILITY.md)
- [UI Implementation Guide](UI-IMPLEMENTATION-GUIDE.md)


## Application-wide interaction and data presentation standards

- [ADR-0013 — Asynchronous Action Drawer Form Submission](../decisions/ADR-0013-asynchronous-action-drawer-form-submission.md)
- [Table Guidelines](TABLE-GUIDELINES.md)
- [Filter Guidelines](FILTER-GUIDELINES.md)

All drawer forms use the shared asynchronous submission contract, including a stable overlaid pending indicator, replacement of validation errors on retries and a reusable floating success toast. All production data tables use a horizontal scroll container with only the first column sticky on the left; the last and all other columns scroll normally. Equal outer spacing and unclipped overlays remain mandatory. Listing filters follow the automatic-filtering and request lifecycle standards. These standards apply to future modules unless an approved, documented exception exists.
