# Responsive Guidelines

## Target viewport classes

| Class | Width |
|---|---|
| Mobile | 320–767 px |
| Tablet | 768–1023 px |
| Desktop | 1024 px and above |

Use responsive CSS based on available space, not device detection.

## Navigation
- Desktop: persistent sidebar and topbar.
- Mobile/tablet: collapsible drawer.
- Drawer must close after navigation and support keyboard interaction.
- Main content must use the available viewport width.

## Dashboard
- Mobile: one card per row.
- Tablet: two cards per row where space permits.
- Desktop: responsive multi-column grid.

## Forms
- Mobile: one column.
- Wider screens: group related fields into columns when useful.
- Labels remain visible; do not rely only on placeholders.
- Primary actions must remain easy to reach.

## Tables

Every production data table MUST use the shared horizontally scrollable table container defined in [Table Guidelines](TABLE-GUIDELINES.md). Horizontal scrolling is the standard table behavior at every viewport; it is not conditional on a developer deciding that comparison is essential.

- Keep only the first column sticky at the left edge while the table scrolls horizontally.
- The last column and every other column MUST scroll normally; do not pin the action column to the right.
- Preserve equal horizontal content padding at the outer left and right edges.
- Use an opaque background, right-edge divider and correct stacking for the first sticky column only.
- Keep the table readable at narrow widths without causing page-level horizontal overflow.
- Do not silently replace the table with cards or change the first-column-only sticky rule. A materially different mobile presentation requires a documented, approved usability/accessibility exception.
- Ensure row menus and overlays are not clipped by scroll containers or ancestor overflow.

## Touch and testing
- Minimum target: 44 × 44 CSS px.
- Test at 320×568, 375×812, 430×932, 768×1024,
  1024×768, 1366×768 and 1920×1080.
- Test Hindi text wrapping and long customer names.
## Contextual action drawer

The action drawer is separate from the navigation drawer. It opens on explicit user action and overlays the current page.

- Desktop (1024 px and above): right-side overlay, approximately 480 CSS px default width; cap width to the available viewport and allow a justified wider variant.
- Tablet: use available width without causing horizontal page overflow; use a near-full-width presentation when needed.
- Mobile (320–767 px): full-screen or near-full-screen drawer. Keep the header and primary action reachable while the content scrolls independently.
- Keep the underlying page's scroll position when opening and closing.
- Prevent background interaction and scrolling while the modal drawer is open, then restore the prior state on close.
- Test at the documented viewport sizes, with long Hindi/English labels, validation errors, browser zoom and the on-screen keyboard.
- Verify drawer submission loading does not change button dimensions, unmount form content or shift the layout.
- Verify floating success toasts remain visible, readable and dismissible at mobile widths.
- Do not confuse the contextual action drawer with the responsive navigation drawer.


## Listing filters

Routine listing filters should apply automatically. Debounce text search (default 350–400 ms), apply discrete filters on change, handle stale requests and preserve usable loading, empty and error states. See [Filter Guidelines](FILTER-GUIDELINES.md).
