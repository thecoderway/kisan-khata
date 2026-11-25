# Table Guidelines

## Purpose and scope

These rules apply to every data table in the authenticated application, including current and future modules. A module may use a different presentation only when a documented usability or accessibility reason justifies the exception.

## Required responsive container

- Every production data table MUST be wrapped in a dedicated horizontally scrollable container.
- The container MUST own horizontal overflow; the page and its main content layout MUST NOT gain page-level horizontal scrolling because of a table.
- The table MUST retain enough intrinsic/minimum width for its columns to remain readable. Do not compress every column until labels, values or controls become unusable.
- The scroll container MUST provide a usable touch and pointer scrolling experience and must not hide focus indicators.
- Provide an accessible name or context for the table and its scroll region where needed. Ensure keyboard users can reach and operate horizontal scrolling.
- Do not nest unnecessary horizontal scroll containers.

## Sticky edge columns

- The first column MUST remain sticky at the left edge while the table scrolls horizontally.
- Every column after the first, including the last/action column, MUST scroll normally with the table. Do not make the last column sticky.
- The first sticky column MUST have an opaque surface background, an appropriate right-edge divider/shadow and a deliberate stacking order so scrolling cells never show through it.
- Header cells must layer correctly above body cells. The intersection of sticky header and sticky edge columns must remain visible and readable.
- The first-column sticky treatment MUST account for its actual width and responsive changes. Do not hardcode offsets that become incorrect when column widths change.
- When a table has only one column, its first column may remain sticky at the left; do not add a second sticky treatment.
- First-column sticky behavior MUST not obscure focus outlines, validation feedback, row content or actionable controls.

## Consistent horizontal spacing

- Use a consistent horizontal cell-padding token across the table.
- The content inset from the table's outer left border to the first column's content MUST match the inset from the last column's content to the outer right border.
- First/last column sticky styling MUST preserve this spacing; do not compensate by adding arbitrary one-off margins to individual pages.
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
