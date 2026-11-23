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
- Prefer compact cards on narrow screens for customer/transaction records.
- Use controlled horizontal scrolling only when tabular comparison is essential.
- Keep important identifiers and financial values visible.
- Never cause page-level horizontal overflow.

## Touch and testing
- Minimum target: 44 × 44 CSS px.
- Test at 320×568, 375×812, 430×932, 768×1024,
  1024×768, 1366×768 and 1920×1080.
- Test Hindi text wrapping and long customer names.
