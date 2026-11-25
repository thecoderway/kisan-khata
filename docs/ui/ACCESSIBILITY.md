# Accessibility

Target: WCAG 2.2 AA.

## Requirements
- Semantic HTML landmarks and heading order.
- Keyboard access to navigation, forms, dialogs and actions.
- Visible keyboard focus.
- Labels associated with form controls.
- Accessible names for icon buttons.
- Sufficient text and non-text contrast.
- Status is not communicated by color alone.
- Validation errors are clearly identified and associated with fields.
- Dialogs and drawers manage focus and support Escape where appropriate.
- Respect reduced-motion preferences.
- Touch targets should be at least 44 × 44 CSS px where practical.

## Testing
- Keyboard-only navigation.
- Browser zoom to 200%.
- Screen-reader spot checks.
- Contrast checks for text, controls and focus indicators.
- Hindi and English content checks.
## Action drawer requirements

The contextual action drawer is modal while open and must meet the same WCAG 2.2 AA target as the rest of the application.

- Use appropriate dialog semantics (for example, `role="dialog"`, `aria-modal="true"`) and an accessible title referenced by the dialog.
- Move focus to a sensible control or heading when opened.
- Keep Tab/Shift+Tab focus within the open modal drawer.
- Return focus to the exact trigger control when closed.
- Support Escape and a visible close button. If an operation is actively committing or cannot safely be dismissed, explain the state and require deliberate handling rather than silently discarding it.
- Make the background inert to pointer and keyboard interaction while open.
- Lock background scrolling while open and restore the previous scroll state on close.
- Ensure every icon-only trigger has an accessible name; provide labels and associate validation errors with fields.
- Announce submission/loading/result feedback appropriately to assistive technology.
- Respect reduced-motion preferences; motion must not be required to understand state.
- Maintain visible focus and practical 44 × 44 CSS px touch targets.

### Drawer accessibility checks

- Open and close using keyboard only.
- Verify focus entry, containment and return.
- Verify Escape and visible close behaviour.
- Verify screen-reader title, controls, errors and status announcements.
- Verify zoom to 200%, Hindi/English text wrapping and mobile keyboard behaviour.
