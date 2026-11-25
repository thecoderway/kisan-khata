# Design System

## Product identity
Kisan Khata — किसान का डिजिटल बहीखाता

The interface should feel trustworthy, simple, professional and agriculture-oriented.

## Technology
- Tabler Core
- Bootstrap-based layout and components supplied by Tabler
- Alpine.js for lightweight interactions
- Laravel Blade for server-rendered views
- Vite for asset compilation

Do not introduce another CSS framework or frontend SPA framework without an architectural decision.

## Visual principles
1. Green is the primary brand color, not a background for every component.
2. White surfaces on a subtle off-white page background.
3. Slate for readable text and secondary information.
4. Use semantic colors consistently for financial and status states.
5. Prefer clear labels over icon-only actions.
6. Keep financial amounts visually prominent.
7. Avoid unnecessary gradients, shadows, animation and visual clutter.

## Layout
- Desktop: sidebar + topbar + content.
- Mobile: collapsible navigation drawer + compact topbar.
- Content should have a consistent maximum readable width where appropriate.
- Forms should be mobile-first.
- Tables must have a mobile presentation strategy.

## Data presentation
- Use Indian currency formatting (₹).
- Do not use floating-point arithmetic for financial calculations.
- UI formatting must not alter stored values.
- Show dates in a consistent Indian-friendly format.
- Never silently translate or overwrite customer-entered names or descriptions.

## Status colors
- Success: green
- Warning: amber
- Danger: red
- Information: blue
- Neutral: slate

Color must not be the only way to communicate status.


## Mandatory theme and component source

- Tabler Core is the application's single approved UI theme and component system. All new or modified authenticated UI must follow Tabler's official components, markup conventions, spacing, responsive behavior and accessibility patterns.
- Use the Tabler components already installed in the project; do not recreate a visually unrelated design or introduce a competing UI framework.
- The Kisan Khata brand palette in [Color Palette](COLOR-PALETTE.md) remains the approved brand customization layered on Tabler.
- When a Tabler component pattern is unclear, consult the official [Tabler preview](https://preview.tabler.io/) and [public source repository](https://github.com/tabler/tabler). Prefer the installed package version over copying markup or APIs from a newer preview version.
- Any deliberate deviation from Tabler requires documented product-owner approval.
