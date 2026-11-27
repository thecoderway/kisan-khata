# Table Guidelines

## Purpose and scope

These rules apply to every data table in the authenticated application, including current and future modules. Pagination is mandatory for every production data table; there is no small-dataset or short-result exemption unless the product owner explicitly approves and documents it.

## Mandatory pagination

- Every data table created or modified in the UI MUST implement pagination. Never render an unbounded or unpaginated data table.
- Use a default page size of 10 or 20 rows. Choose 10 for dense/large listings and 20 where row content and viewport support it.
- Provide a page-size selector with options 10, 20, 50 and 100.
- Provide Previous and Next navigation controls. Previous MUST be disabled on the first page; Next MUST be disabled on the last page.
- Display a current-range and total-count indicator, such as "Showing 1–10 of 245". For an empty result set, show a clear zero-result state without an invalid range.
- Pagination MUST be server-side. Backend queries must retrieve only the requested page; never fetch the complete dataset and paginate only in JavaScript/browser memory.
- Pass the current page and page-size parameter (`page` and `limit` or `pageSize`) between frontend and backend. Use one consistent parameter naming convention per endpoint and document it.
- Validate page and page size server-side. Enforce a maximum page size of 100 and reject or safely normalize invalid values. Do not trust client-supplied limits.
- Preserve active filters, search terms and sort state when moving between pages or changing page size. When filters change, reset to page 1.
- Return a stable total count from the server for the current filtered query. Do not derive total count from the current page length.
- Pagination controls must be keyboard accessible, have clear accessible names, and expose disabled/current-page state semantically.
- For AJAX listings, keep page, page size, filters and sort state synchronized with the URL where practical. Cancel or ignore stale requests and do not replace current results on a failed request.
- Any exception must be explicitly approved by the product owner and documented with its rationale. Do not silently omit pagination for small datasets, lookup tables or short current result sets.

## Required responsive container

- Every production data table MUST be wrapped in a dedicated horizontally scrollable container.
- The container MUST own horizontal overflow; the page and its main content layout MUST NOT gain page-level horizontal scrolling because of a table.
- The table MUST retain enough intrinsic/minimum width for its columns to remain readable. Do not compress every column until labels, values or controls become unusable.
- The scroll container MUST provide a usable touch and pointer scrolling experience and must not hide focus indicators.
- Provide an accessible name or context for the table and its scroll region where needed. Ensure keyboard users can reach and operate horizontal scrolling.
- Do not nest unnecessary horizontal scroll containers.

## Sticky first column

- The first column MUST remain sticky at the left edge while the table scrolls horizontally.
- Every column after the first, including the last/action column, MUST scroll normally with the table. Do not make the last column sticky.
- The first sticky column MUST have an opaque surface background, an appropriate right-edge divider/shadow and a deliberate stacking order so scrolling cells never show through it.
- Header cells must layer correctly above body cells. The intersection of the sticky header and sticky first column must remain visible and readable.
- The first-column sticky treatment MUST account for its actual width and responsive changes. Do not hardcode offsets that become incorrect when column widths change.
- When a table has only one column, its first column may remain sticky at the left; do not add a second sticky treatment.
- First-column sticky behavior MUST not obscure focus outlines, validation feedback, row content or actionable controls.

## Consistent horizontal spacing

- Use a consistent horizontal cell-padding token across the table.
- The content inset from the table's outer left border to the first column's content MUST match the inset from the last column's content to the outer right border.
- First-column sticky styling MUST preserve this spacing; do not compensate by adding arbitrary one-off margins to individual pages.
- Align headers and body cells consistently. Numeric and currency alignment may differ by data type, but the cell's horizontal padding remains consistent.
- Verify spacing at desktop, tablet and mobile widths.

## Table height and short result sets

- A table with one or very few records MUST retain a usable visual height and not collapse into an awkwardly short strip.
- Define minimum height at the table viewport or containing results region where appropriate; do not add fake records or excessive blank rows.
- Short result sets MUST still display empty space and pagination/summary controls in a balanced way.
- Table height is not a substitute for correct overlay behavior: dropdowns, menus, tooltips and popovers MUST remain fully visible and usable.

## Dropdowns and overlays inside tables

- Row action menus MUST NOT be clipped by the table scroll container, card, or another ancestor overflow boundary.
- Increasing z-index alone is not an acceptable fix when an ancestor clips descendants.
- Choose an overlay/positioning strategy compatible with the existing Tabler/Bootstrap and Popper setup. Verify menus near the top, bottom, left and right edges of the scroll region and viewport.
- Menus MUST remain anchored to their trigger, fit within the viewport, support keyboard interaction and close appropriately after selection or dismissal.
- Test the one-row case and the first, middle and final visible rows.

## States and behavior

Every table/listing MUST provide appropriate:
- loading state;
- empty state;
- error state;
- populated state;
- pagination or progressive-loading state where applicable.

Do not present stale results as if they were current after a failed refresh. Preserve the user's current filter and page context when updating rows after a drawer action.

## Accessibility

- Use semantic table markup with correctly associated headers.
- Keep row actions keyboard accessible with accessible names.
- Ensure sticky layers do not cover keyboard focus.
- Use visible focus states and sufficient contrast.
- Do not communicate status using color alone.
- On narrow screens, verify the first sticky column does not consume so much width that the remaining table becomes unusable. If the data model needs a different mobile presentation, document and approve the exception rather than silently dropping required columns.

## Acceptance checklist

- [ ] Table is inside a horizontally scrollable container.
- [ ] No page-level horizontal overflow is introduced.
- [ ] First column remains visible on the left during horizontal scrolling.
- [ ] Last column and all other non-first columns scroll normally.
- [ ] First-column background, divider, stacking and focus states are correct.
- [ ] Left and right outer content insets are equal.
- [ ] One-row and few-row tables have a balanced minimum height.
- [ ] Action dropdowns are not clipped and remain in the viewport.
- [ ] Loading, empty, error and populated states are handled.
- [ ] Desktop, tablet and mobile viewports are checked.


## Financial listing API parameter convention

The Sales and Payments APIs use Laravel paginator query parameters consistently: `page` and `per_page`. The accepted page sizes are 10, 20, 50 and 100; the default is 20 and the server enforces the 100-row maximum. Sales additionally supports `q`, `from`, `to`, `payment_status` and `sort`. Payments supports `q`, `from`, `to`, `payment_method` and `sort`. Search and filters are applied by the tenant-scoped backend query before pagination.
