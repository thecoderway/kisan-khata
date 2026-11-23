
# Bilingual UX Rules

These rules apply to all user-facing Kisan Khata functionality.

## 1. Supported Languages

The application supports:

- Hindi (hi)
- English (en)

Do not introduce additional languages without product approval.

## 2. Localization

Use Laravel localization.

Store interface translations in language files.

Suggested structure:

lang/
    en/
        messages.php
        validation.php
        accounting.php
    hi/
        messages.php
        validation.php
        accounting.php

Follow the localization conventions supported by the installed Laravel version.

## 3. No Hardcoded UI Text

Do not hardcode visible interface labels inside Blade templates or controllers.

Use translation keys.

Example:

```php
__('messages.add_customer');
```

Translation files must contain both Hindi and English values.

## 4. Consistent Terminology

Use consistent business terminology.

Examples:

- Customer = ग्राहक
- Credit Sale = उधार बिक्री
- Payment = जमा राशि
- Outstanding = बकाया
- Principal = मूलधन
- Interest = ब्याज
- Ledger = खाता बही
- Settlement = हिसाब चुकता

Do not randomly switch between different Hindi terms for the same concept.

## 5. Customer Data

Customer-entered information may contain Hindi or English text.

Support both scripts.

Do not assume automatic transliteration is always correct.

Do not overwrite the original customer-entered value with a generated translation.

## 6. Search

Search must support Hindi and English text.

Do not assume that searching a Hindi name automatically finds its English spelling.

If transliteration or alternate-name search is introduced, preserve original values and document the search behavior.

## 7. Dates and Numbers

Use consistent formatting.

Support Indian currency display.

Example:

₹1,25,000.00

Business-facing dates should use the shop's configured timezone.

Do not change stored financial amounts when changing display language.

## 8. Validation Messages

Validation messages must be localized.

Errors must explain what the user needs to correct.

Avoid technical database or programming terminology.

## 9. Forms

Use simple labels.

Avoid unnecessarily complex forms.

Use clear confirmation messages for financial operations.

## 10. Accessibility

Ensure:

- Devanagari text renders correctly.
- Font sizes remain readable.
- Text contrast is sufficient.
- Buttons have descriptive labels.
- Inputs have associated labels.
- Mobile screens are usable.

## 11. Print Output

Ledger printing must support Hindi and English.

Verify Devanagari font rendering in the selected print or PDF implementation.

Do not assume a font available in the developer's computer is available on the production server.

## 12. Testing

Test:

- Hindi labels.
- English labels.
- Hindi validation messages.
- English validation messages.
- Hindi customer names.
- English customer names.
- Mixed-script search.
- Hindi print output.
