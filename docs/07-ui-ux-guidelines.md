# Kisan Khata — UI/UX Guidelines

**Document Version:** 2.0  
**Status:** Approved  
**Languages:** Hindi and English  
**Primary Users:** Indian agricultural merchants, traders and shop operators  
**Platforms:** Responsive Web Application; future Android application

---

## 1. Design Philosophy

Kisan Khata must be simple, fast, reliable and easy to understand.

The application is intended for users who may have limited experience with accounting software.

The interface must prioritize:

1. Clarity.
2. Simplicity.
3. Readability.
4. Fast data entry.
5. Mobile responsiveness.
6. Financial transparency.
7. Hindi and English support.
8. Error prevention.
9. Consistent user experience.

Users should be able to perform everyday shop operations without requiring accounting or technical knowledge.

## 2. Language Support

The application must support:

- Hindi.
- English.

Requirements:

- Users can select their preferred interface language.
- Language preference must persist across sessions.
- All interface labels, buttons, validation messages and notifications must be translatable.
- Use Laravel localization files.
- Avoid hardcoded interface strings inside controllers and Blade templates.
- Support Devanagari and Latin scripts throughout the application.

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

### Customer Data Language

Customer-entered data may be entered in the language selected by the user.

Where automatic translation is enabled:

- Preserve the original entered text.
- Store Hindi and English translations separately.
- Never overwrite original text with a translated version.
- Allow authorized correction of translated text.
- Display the appropriate language version according to interface settings.

## 3. Hindi Terminology

Use simple and familiar Hindi.

| English | Hindi |
|---|---|
| Dashboard | मुख्य पृष्ठ |
| Customer | ग्राहक |
| Add Customer | ग्राहक जोड़ें |
| Credit Sale | उधार बिक्री |
| Payment | जमा राशि |
| Outstanding | कुल बकाया |
| Interest | ब्याज |
| Ledger | खाता बही |
| Settlement | हिसाब चुकता |
| Village | गाँव |
| Print Ledger | खाता प्रिंट करें |
| Transaction | लेन-देन |
| Advance | अग्रिम जमा |
| Opening Balance | प्रारंभिक बकाया |
| Closing Balance | अंतिम बकाया |
| Total Payable | कुल देय राशि |
| Auto Settlement | स्वतः हिसाब प्रक्रिया |

Terminology must remain consistent across the application.

Avoid displaying complex accounting terms unless they are necessary.

## 4. Application Layout

Use a responsive layout optimized for desktop and mobile browsers.

### Desktop

- Sidebar navigation.
- Top header.
- Main content area.
- Clear page title.
- Primary action button.
- Contextual actions.
- Consistent page spacing.

### Mobile

- Compact navigation.
- Large touch targets.
- Simple forms.
- Minimal horizontal scrolling.
- Important actions easily accessible.
- Readable tables or mobile-friendly transaction lists.

Avoid overloading pages with too many cards or unnecessary information.

## 5. Navigation Structure

Suggested main navigation:

1. Dashboard.
2. Customers.
3. Sales.
4. Payments.
5. Customer Ledger.
6. Settlements.
7. Reports.
8. Staff Management.
9. Shop Settings.
10. Subscription.

Navigation items must be displayed according to the authenticated user's role.

Staff should not see Owner-only settings and subscription controls.

Managers should not see unauthorized administrative actions.

## 6. Dashboard

The dashboard should provide a clear overview of shop activity.

Suggested information:

- Total customers.
- Total outstanding.
- Today's credit sales.
- Today's collections.
- Customer advances.
- Recent transactions.
- Important notifications.

Possible sections:

- Recent sales.
- Recent payments.
- Customers with outstanding balances.
- Quick actions.
- Upcoming Auto Settlement dates, where configured.

Requirements:

- All figures must be tenant-scoped.
- Financial totals must come from authoritative backend calculations.
- Dashboard figures must reflect the correct business date and shop timezone.
- Do not calculate authoritative financial balances exclusively in JavaScript.
- Display the last updated time where data may be cached.

## 7. Customer List

Display:

- Customer name.
- Mobile number, if available.
- Village, if available.
- Current outstanding balance.
- Customer status.
- Actions.

Features:

- Search.
- Pagination.
- Village filter.
- Outstanding balance filter.
- Add customer action.
- Sort by name, outstanding or recent activity where appropriate.

Search should support Hindi and English text.

On mobile, display essential customer information first.

Customer records must always belong to the current shop.

## 8. Customer Form

Suggested fields:

- Customer name (required).
- Mobile number (optional).
- Village (optional).
- Address (optional).
- Notes (optional).

Requirements:

- Clear labels.
- Required field indicators.
- Helpful validation messages.
- Large input fields.
- Mobile-friendly keyboard types.
- Avoid unnecessary fields.
- Preserve original entered text.
- Clearly indicate optional fields.

Do not require customer mobile number or village.

## 9. Credit Sale Form

A credit sale requires:

- Customer selection.
- Sale date.
- Amount.
- Description.

Requirements:

- Customer search.
- Clear amount display.
- Sale date defaults to current shop date where appropriate.
- Description should support practical details such as crop, season or goods.
- Confirmation before posting.
- Show validation errors next to fields.
- Prevent accidental duplicate submission.

Do not introduce mandatory sale categories or product types.

Crop or season information may be included in the description or supported tagging functionality.

Do not calculate or finalize financial values exclusively in JavaScript.

## 10. Payment Form

Suggested fields:

- Customer.
- Payment amount.
- Payment date.
- Payment method, if enabled.
- Reference number, if applicable.
- Description or notes.

After successful payment posting, show:

- Customer name.
- Amount received.
- Payment date.
- Previous payable amount.
- Interest calculated through the payment date, where applicable.
- New outstanding balance.
- Advance amount, if payment exceeds total payable.

### Payment UX Rules

The interface must explain that payments reduce the customer's consolidated payable balance.

Do not ask users to select individual sales for payment allocation.

Do not display oldest-sale or newest-sale allocation options.

Multiple payments on the same date must not create duplicate interest for the same elapsed period.

The success screen must be displayed only after confirmation from the backend.

## 11. Customer Ledger Screen

The customer ledger is one of the most important screens.

It must present a consolidated customer account.

### Customer Summary

Show:

- Customer information.
- Current outstanding balance.
- Customer advance, if any.
- Applicable interest rate.
- Last financial activity date.

### Ledger Entries

Show relevant entries such as:

- Opening balance.
- Credit sales.
- Payments.
- Interest calculations.
- Interest adjustments, where applicable.
- Settlements.
- Carry-forward entries.
- Authorized transaction corrections.

Each entry should show:

- Date.
- Description.
- Transaction type.
- Debit or credit effect.
- Resulting balance.

### Consolidated Balance Presentation

The interface must not imply that payments are allocated to individual sale IDs.

After a payment:

1. Calculate eligible interest through the payment date.
2. Add it to the amount payable according to the configured interest policy.
3. Subtract the payment received.
4. Display the resulting consolidated outstanding balance.
5. Begin the next calculation cycle from the payment date for the carried-forward balance.

New sales after that payment must retain their own sale dates for subsequent interest calculations.

The ledger must make this behavior understandable without exposing unnecessary accounting complexity.

## 12. Interest Display

Interest must be transparent and explainable.

Where applicable, show:

- Applicable monthly rate.
- Calculation start date.
- Calculation end date.
- Number of eligible days.
- Calculation basis.
- Interest amount.
- Relevant outstanding balance or sale basis.
- Interest policy.
- Calculation date.

### Interest Explanation

Provide a simple explanation of how interest was calculated.

Example:

```text
Outstanding amount: ₹10,000
Monthly interest rate: 2%
Eligible days: 15

Interest:
₹10,000 × 2% × 15 / 30

Interest amount: ₹100
```

The example is illustrative.

The backend remains authoritative for actual calculations, including grace period, capitalization policy and applicable balance rules.

Never display unexplained interest charges.

## 13. Interest Policy Settings

Shop Owner can configure the approved shop-level interest settings.

Where supported, customer-specific rates may override the shop default.

Settings may include:

- Monthly interest rate.
- Grace period.
- Interest capitalization policy.
- Annual Auto Settlement dates.

Requirements:

- Explain each setting in simple language.
- Show the effect of changing a policy.
- Require confirmation before saving sensitive changes.
- Record changes in audit history.
- Do not silently modify historical financial calculations.

## 14. Settlement Screen

The settlement screen should provide a clear summary before posting.

Show:

- Current outstanding balance.
- Applicable interest through settlement date.
- Total payable amount.
- Amount received.
- Remaining balance.
- Customer advance, if applicable.
- Carry-forward amount.
- Next crop season or settlement reference, where relevant.

Support:

- Full settlement.
- Partial settlement.
- Carry-forward of remaining balance.

Require confirmation before posting a settlement.

Display the exact financial effect before confirmation.

## 15. Transaction Correction UX

Only Shop Owner may edit posted sales and payments under approved correction rules.

Requirements:

- Clearly distinguish editable and non-editable transactions.
- Show an Edit action only to authorized users.
- Require confirmation before saving corrections.
- Capture a correction reason where required.
- Show original and updated values in audit history.
- Explain when a correction affects outstanding balance or interest.
- Display the updated authoritative balance after successful processing.

Manager and Staff must not receive edit or delete controls for posted financial transactions.

Never silently alter historical records.

## 16. Staff Management

Owner can create and manage Manager and Staff accounts.

Display shop capacity:

| Role | Maximum |
|---|---:|
| Owner | 1 |
| Manager | 2 |
| Staff | 5 |
| Total | 8 |

Requirements:

- Show current and available role capacity.
- Prevent creating accounts beyond capacity.
- Clearly display each user's role and status.
- Require confirmation for deactivation or role changes.
- Enforce permissions on the backend.

## 17. Colors and Status

Use consistent visual semantics.

Suggested usage:

- Green: Successful payment or positive confirmation.
- Red: Outstanding warning or failed operation.
- Blue: Informational status.
- Grey: Neutral or inactive status.
- Amber: Pending action or attention required.

Do not rely on color alone.

Always include readable labels, icons or status descriptions.

## 18. Typography

Use fonts that support Devanagari and Latin scripts.

Ensure:

- Hindi characters render correctly.
- Numbers remain readable.
- Amounts are visually prominent.
- Tables remain legible.
- Print output uses appropriate fonts.
- Text remains readable on small mobile screens.

## 19. Date and Currency

Default currency:

Indian Rupee (INR).

Example:

```text
₹1,25,000.00
```

Requirements:

- Use Indian digit grouping.
- Use consistent decimal precision for currency.
- Use a clearly defined timezone strategy.
- Use shop timezone for business-facing dates.
- Display dates consistently across forms, ledgers and reports.
- Avoid ambiguous date formats.

## 20. Forms and Validation

Forms should:

- Use clear labels.
- Explain errors.
- Preserve entered values after validation failures.
- Avoid unnecessary steps.
- Confirm destructive or sensitive operations.
- Prevent accidental duplicate submissions.
- Show loading states during submission.
- Disable repeated submission while a request is processing.
- Display backend-confirmed success or failure messages.

Financial actions must show a confirmation summary before posting.

## 21. Notifications and Feedback

Provide clear feedback for:

- Successful customer creation.
- Successful sale posting.
- Successful payment posting.
- Transaction correction.
- Settlement posting.
- Validation failures.
- Authorization failures.
- Subscription status.
- Network or server errors.

Never show a success message before the backend confirms the operation.

Error messages should explain what the user can do next without exposing technical internals.

## 22. Accessibility

Requirements:

- Adequate contrast.
- Keyboard accessibility.
- Visible focus states.
- Labels for inputs.
- Descriptive buttons.
- Readable font sizes.
- Touch-friendly controls.
- Appropriate screen-reader labels.
- Do not use color as the only status indicator.

## 23. Printing and Export

Customer ledger printing must use a dedicated print layout.

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
- Clear currency formatting.

Avoid printing unnecessary navigation or application controls.

Support authorized PDF and Excel/CSV exports where applicable.

Shop Owner must retain permitted data export access after subscription expiry.

## 24. Responsive Design

Test at:

- Desktop.
- Laptop.
- Tablet.
- Small Android phone.
- Large Android phone.

The interface must remain usable at common mobile widths.

Tables should adapt to mobile-friendly transaction lists or responsive layouts.

Do not force users to scroll horizontally for routine operations wherever avoidable.

## 25. Role-Based UI

The interface must adapt to the authenticated user's role.

### Owner

Full shop interface, including:

- Shop settings.
- Subscription.
- Staff management.
- Interest policies.
- Financial corrections.
- Reports and exports.

### Manager

Operational interface, including:

- Customers.
- Sales.
- Payments.
- Reports.
- Routine staff operations.

No subscription, ownership or critical security controls.

No posted financial transaction correction controls.

### Staff

Limited operational interface, including:

- Customer viewing and creation.
- Credit sales.
- Payment recording.
- Own work and transaction history.

No posted financial transaction correction controls.

Frontend visibility is not a substitute for backend authorization.

## 26. UX Testing

Test with users who are not software professionals.

Observe whether they can independently:

1. Log in.
2. Add a customer.
3. Record a credit sale.
4. Record a payment.
5. Find a customer.
6. Understand outstanding balance.
7. Understand an interest calculation.
8. View customer ledger.
9. Print a ledger.
10. Complete a settlement where authorized.

Collect feedback and improve confusing screens.

Test both Hindi and English interfaces.

## 27. Acceptance Criteria

The interface is acceptable when:

- Hindi and English work consistently.
- Customer data supports the approved language strategy.
- Forms are easy to understand.
- Customer search is effective.
- Financial values are clear.
- Consolidated customer balances are understandable.
- Payments do not require sale-level allocation.
- Interest calculations are explainable.
- Owner-only correction controls are enforced.
- Role-based navigation is consistent.
- Ledger history is readable.
- Mobile screens are usable.
- Printing and exports work correctly.
- Backend-confirmed results are displayed accurately.
