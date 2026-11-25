# Page Layouts

## Authenticated application shell
- Sidebar/drawer navigation
- Topbar with page title, language and account controls
- Main content area
- Consistent page-level action placement

## Initial page inventory

### Authentication
- Login
- Registration
- OTP verification
- Password reset

### Dashboard
- Customer count
- Sales summary
- Payments summary
- Outstanding summary
- Recent activity

### Customers
- Customer list and search
- Customer profile
- Customer ledger

### Transactions
- Add sale
- Add payment
- Transaction history

### Reports
- Sales
- Payments
- Outstanding
- Customer statements

## Page rules
- Keep the page title and primary action easy to find.
- Use breadcrumbs for deep navigation.
- Provide loading, empty and error states.
- Do not expose data belonging to another shop.
- Dashboard figures must come from authorized backend queries in the real application.

## Prototype limitation
The initial dashboard prototype uses illustrative values only.
It is not a functional accounting interface.
## Contextual action drawer and page decision

The application-wide default for routine Create, Edit and record-level actions is the shared right-side overlay drawer (see [ADR-0012](../decisions/ADR-0012-application-wide-right-side-drawer-standard.md)).

- Listing pages show record data, status, search/filter/pagination and a compact action trigger.
- Do not render per-record action forms or reason inputs inline in table rows.
- Add, Edit, Deactivate/Reactivate, Soft Delete/Restore and similar routine actions open the drawer after an explicit click.
- The drawer overlays the current page; it does not push the page content.
- Keep the list's scroll position and context when the drawer closes.

### When to use a dedicated page

A full page is an exception for multi-step workflows, complex editable tables/calculations, or forms/workspaces that need substantial simultaneous width or several independent sections. Field count alone is not enough. Record the reason in the design/implementation review. Prefer a drawer when uncertain and validate with actual viewport sizes and user tasks.

### Initial module placement guidance

| Module/task | Default presentation |
|---|---|
| Customer create/edit and lifecycle actions | Drawer |
| User create/edit and routine role/lifecycle actions | Drawer |
| Simple settings | Drawer |
| Shop profile | Assess complexity; drawer by default, full page only with documented justification |
| Large financial workspace or multi-step settlement | Dedicated page when complexity requires it |
| Reports and customer ledger | Dedicated page |
