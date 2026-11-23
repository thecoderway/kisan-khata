# Bilingual UX Rules

The supported UI languages are Hindi (hi) and English (en). Use Laravel localization and translation keys; do not hardcode visible UI text. Keep terminology simple and consistent for shop users.

## Customer-entered data
- Preserve the exact original customer-entered text.
- Store Hindi and English translations separately from the original.
- Automatic translation must never overwrite the original.
- Allow authorized correction of translation fields while preserving original text.
- Do not assume transliteration or translated spellings are exact equivalents.
- Search behavior across scripts must be explicit and tested.

## Presentation
Use consistent Hindi/English terminology, shop timezone for business-facing dates, Indian currency display, localized validation messages and readable forms. Language changes must never change stored monetary values.

## Accessibility and print
Verify Devanagari fonts on production print/PDF output, readable sizes, contrast, labels and mobile usability.

## Tests
Cover both locales, validation, original-text preservation, separately stored translations, translation corrections, mixed-script search and Hindi print rendering.