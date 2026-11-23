
# Kisan Khata — UI/UX Guidelines

**Document Version:** 1.0  
**Languages:** Hindi and English  
**Primary Users:** Small-town Indian merchants

---

## 1. Design Philosophy

Kisan Khata must be simple, fast and easy to understand.

The target users may have limited experience with accounting software.

The interface should feel familiar and should not require technical knowledge.

Priorities:

1. Clarity.
2. Simplicity.
3. Readability.
4. Fast data entry.
5. Mobile responsiveness.
6. Financial transparency.
7. Hindi and English support.

## 2. Language Support

The application must support:

- Hindi
- English

Users should be able to switch languages.

Use Laravel localization files.

Suggested structure:

```text
lang/
    en/
        messages.php
        validation.php
        accounting.php
    hi/
        messages.php
        validation.php
        accounting.php
```

All visible interface labels must be translatable.

Avoid hardcoded UI strings inside controllers and Blade templates.

## 3. Hindi Terminology

Use simple, familiar Hindi.

Suggested terms:

| English | Hindi |
|---|---|
| Dashboard | मुख्य पृष्ठ |
| Customer | ग्राहक |
| Add Customer | ग्राहक जोड़ें |
| Credit Sale | उधार बिक्री |
| Payment | जमा राशि |
| Outstanding | बकाया |
| Principal | मूलधन |
| Interest | ब्याज |
| Ledger | खाता बही |
| Settlement | हिसाब चुकता |
| Village | गाँव |
| Print Ledger | खाता प्रिंट करें |
| Transaction | लेन-देन |

Terminology should remain consistent throughout the application.

## 4. Layout

Use a responsive layout.

Desktop:

- Sidebar navigation.
- Top header.
- Main content.
- Clear page title.
- Primary action button.

Mobile:

- Compact navigation.
- Large touch targets.
- Simple forms.
- Minimal horizontal scrolling.
- Important actions easily accessible.

Do not overload the dashboard with too many cards.

## 5. Dashboard

The dashboard should show useful business information.

Suggested cards:

- Total customers
- Total outstanding
- Today's sales
- Today's collections
- Pending balances
- Recent transactions

Possible sections:

- Recent credit sales
- Recent payments
- Customers with outstanding balances
- Quick actions

Every dashboard figure must be tenant-scoped.

Financial totals must come from authoritative backend calculations.

## 6. Customer List

Show:

- Customer name
- Father's name
- Mobile number
- Village
- Outstanding balance
- Actions

Features:

- Search.
- Pagination.
- Village filter.
- Outstanding filter.
- Add customer action.

Search should work with Hindi and English text.

On mobile, show essential customer details first.

## 7. Customer Form

Fields:

- Name
- Father's name
- Mobile
- Alternate mobile
- Village
- Address
- PIN code
- Notes

Requirements:

- Clear labels.
- Required field indicators.
- Helpful validation messages.
- Large inputs.
- Mobile-friendly keyboard types.
- Avoid unnecessary fields.

## 8. Credit Sale Form

Fields:

- Customer selection
- Amount
- Sale date
- Description
- Bill number
- Bill photo
- Notes

Requirements:

- Customer search.
- Clear amount display.
- Date defaults to current date where appropriate.
- Confirmation before posting.
- Show validation errors next to fields.

Do not calculate or finalize financial values exclusively in JavaScript.

## 9. Payment Form

Fields:

- Customer
- Payment amount
- Date
- Payment method
- Reference number
- Description
- Receipt photo

After recording a payment, show:

- Amount received
- Customer name
- Payment date
- Reference number
- Resulting balance, if available from the authoritative backend response

Do not imply that payment posting succeeded before the server confirms it.

## 10. Customer Ledger Screen

The ledger is one of the most important screens.

Show:

- Customer information.
- Opening balance.
- Transactions.
- Principal balance.
- Interest balance.
- Total outstanding.
- Closing balance.

Each entry should clearly explain why the balance changed.

Use consistent debit and credit labels.

Avoid confusing accounting terminology where a simpler explanation is possible.

Provide date range filters.

## 11. Interest Display

Interest must be transparent.

Where applicable, show:

- Applicable rate.
- Calculation period.
- Principal basis.
- Interest amount.
- Policy reference.
- Calculation explanation.

Never display unexplained interest charges.

## 12. Settlement Screen

Show:

- Current principal.
- Outstanding interest.
- Amount received.
- Remaining amount.
- Carry-forward details.

Ask for confirmation before posting a settlement.

Show a summary of the exact financial effect.

## 13. Colors and Status

Use consistent visual semantics.

Suggested usage:

- Green: Payment received or positive confirmation.
- Red: Outstanding or warning.
- Blue: Informational.
- Grey: Neutral or inactive.

Do not rely on color alone.

Always include readable labels or icons.

## 14. Typography

Use fonts that support Devanagari and Latin scripts.

Ensure:

- Hindi characters render correctly.
- Numbers remain readable.
- Amounts are visually prominent.
- Tables remain legible.
- Print output uses appropriate fonts.

## 15. Date and Currency

Default currency:

Indian Rupee (INR).

Display:

₹1,25,000.00

Use Indian digit grouping where appropriate.

Support consistent date formatting.

Store dates and timestamps using a clearly defined timezone strategy.

The shop timezone should be used for business-facing dates.

## 16. Forms and Validation

Forms should:

- Use clear labels.
- Explain errors.
- Preserve entered values after validation failures.
- Avoid unnecessary steps.
- Confirm destructive or sensitive operations.
- Prevent accidental duplicate submissions.

Financial actions should show a confirmation summary.

## 17. Accessibility

Requirements:

- Adequate contrast.
- Keyboard accessibility.
- Visible focus states.
- Labels for inputs.
- Descriptive buttons.
- Readable font sizes.
- Touch-friendly controls.

## 18. Printing

Ledger printing must use a dedicated print layout.

Requirements:

- A4 page support.
- Shop details.
- Customer details.
- Date range.
- Opening balance.
- Transaction table.
- Running balance.
- Closing balance.
- Page numbering.
- Hindi font support.

Avoid printing unnecessary navigation or application controls.

## 19. Responsive Design

Test at:

- Desktop.
- Laptop.
- Tablet.
- Small Android phone.
- Large Android phone.

The interface must remain usable at common mobile widths.

## 20. UX Testing

Test with users who are not software professionals.

Observe whether they can independently:

1. Add a customer.
2. Record credit sale.
3. Record payment.
4. Find a customer.
5. View outstanding balance.
6. Print a ledger.

Use actual feedback to improve the interface.

## 21. Acceptance Criteria

The interface is acceptable when:

- Hindi and English work.
- Forms are easy to understand.
- Customer search is effective.
- Financial values are clear.
- Ledger history is readable.
- Mobile screens are usable.
- Printing works correctly.
