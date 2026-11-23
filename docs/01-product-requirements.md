
# Kisan Khata — Product Requirements Document (PRD)

**Document Version:** 1.0  
**Product:** Kisan Khata  
**Product Type:** Multi-Tenant SaaS Accounting Application  
**Initial Platform:** Responsive Web Application  
**Future Platform:** Android Application

---

## 1. Product Overview

Kisan Khata is a cloud-based accounting and customer credit management platform designed for small and medium-sized merchants in Indian towns and villages.

Many merchants sell products to farmers on credit and receive payments after harvesting seasons.

These merchants commonly maintain handwritten notebooks to track:

- Customer details
- Credit sales
- Payments
- Outstanding balances
- Interest
- Seasonal settlements

Kisan Khata will digitize this process and provide accurate, transparent and printable customer ledgers.

## 2. Product Goals

1. Replace manual credit notebooks.
2. Make customer account management simple.
3. Track every credit sale and payment.
4. Calculate interest according to shop-specific approved policies.
5. Support seasonal account settlements.
6. Provide complete customer-wise financial history.
7. Support Hindi and English.
8. Allow multiple independent shops to use one platform.
9. Generate revenue through subscriptions.
10. Prepare the architecture for a future Android application.

## 3. Target Users

### 3.1 Primary Users

- Cloth merchants
- Electronics retailers
- Agricultural input shops
- Fertilizer retailers
- Seed retailers
- Pesticide retailers
- General merchants

### 3.2 Typical Customer

A farmer who purchases products on credit and pays after crop harvesting.

## 4. User Roles

### Platform Owner

Responsible for the SaaS platform.

Capabilities:

- Manage shops
- Manage subscriptions
- View platform-level usage
- Manage subscription plans
- Suspend or reactivate shops
- View platform audit information

Platform administrators must not automatically receive unrestricted access to tenant financial records.

### Shop Owner

Responsible for an individual merchant account.

Capabilities:

- Manage shop profile
- Manage shop employees
- Manage customers
- Create credit sales
- Record payments
- View customer ledgers
- Configure approved interest policies
- Create settlements
- Generate reports
- Manage subscription

### Shop Employee

Capabilities must be configurable by the shop owner.

Possible permissions:

- View customers
- Add customers
- Create sales
- Record payments
- View ledgers
- Print ledgers
- View reports

Sensitive operations such as changing financial policies, reversing posted transactions and creating settlements should require explicit permissions.

## 5. Authentication and Shop Registration

The system should support:

- User registration
- Login
- Logout
- Password reset
- Email or mobile verification, depending on the selected authentication design
- Shop registration
- Shop profile creation
- Shop membership management

A user may eventually belong to more than one shop.

Every operation must execute within an authorized shop context.

## 6. Shop Profile

Fields:

- Shop name
- Owner name
- Mobile number
- Email (optional)
- Address
- Village
- Tehsil
- District
- State
- PIN code
- Preferred language
- Logo (optional)

Shop profile information should appear on printable customer ledgers.

## 7. Customer Management

### Customer Information

Required fields:

- Customer name
- Father's name
- Mobile number
- Village

Optional fields:

- Address
- PIN code
- Notes
- Alternate mobile number

### Customer Operations

- Add customer
- View customer
- Edit customer
- Search customers
- Filter customers
- View customer ledger
- View outstanding balance

### Search Requirements

Support search by:

- Customer name
- Father's name
- Mobile number
- Village

Search must support Hindi and English text.

Do not rely on automatic transliteration as the only search mechanism.

## 8. Credit Sales Management

A shop owner or authorized employee can record a credit sale.

### Sale Fields

- Customer
- Sale amount
- Sale date
- Sale time
- Description
- Bill number (optional)
- Bill photo (optional)
- Notes (optional)

### Sale Operations

- Create sale
- View sale
- Edit draft sale
- View sale history
- Search sales
- Filter by customer
- Filter by date
- Filter by amount
- Print sale details

Posted sales must follow the approved correction and reversal policy.

## 9. Payment Management

A payment represents money received from a customer.

### Payment Fields

- Customer
- Amount
- Payment date
- Payment time
- Payment method
- Reference number
- Description
- Receipt photo (optional)

### Payment Methods

- Cash
- UPI
- Bank transfer
- Cheque
- Other

### Payment Operations

- Add payment
- View payment
- Edit draft payment
- View payment history
- View allocation history
- Search payments
- Print receipt

A posted payment must never be silently overwritten.

## 10. Customer Ledger

Every customer must have a complete financial ledger.

### Ledger Entries

- Opening balance
- Credit sale
- Payment
- Interest
- Crop-season settlement
- Adjustment
- Reversal

### Ledger Columns

- Date
- Transaction reference
- Particulars
- Debit
- Credit
- Principal balance
- Interest balance
- Total outstanding

### Ledger Features

- Customer-wise history
- Date range filtering
- Running balance
- Opening and closing balance
- Principal and interest separation
- Printable format
- Hindi and English labels

## 11. Interest Configuration

Each shop may configure its interest policy after the applicable rules are finalized.

Possible configuration fields:

- Interest rate
- Rate period
- Interest-free period
- Calculation method
- Day-count convention
- Partial-month policy
- Compounding policy
- Payment allocation policy
- Rounding policy
- Effective date

Do not activate a policy until its financial semantics are explicitly defined.

## 12. Crop-Season Settlement

The application should support account settlement around harvest seasons.

A settlement may involve:

- Customer
- Settlement date
- Outstanding principal
- Outstanding interest
- Amount paid
- Amount carried forward
- Settlement reference
- Notes

The exact accounting treatment must follow the approved settlement policy.

Settlement must preserve the original transaction history.

## 13. Reports

Initial reports:

1. Customer list
2. Outstanding balance report
3. Customer-wise ledger
4. Credit sales report
5. Payment report
6. Interest report
7. Settlement report
8. Daily collection report
9. Date-wise transaction report

Reports must be tenant-scoped.

## 14. Printing

Customer ledger print format should include:

- Shop name and contact information
- Customer name
- Father's name
- Village
- Mobile number
- Selected date range
- Opening balance
- Transaction details
- Debit and credit
- Running balance
- Principal and interest breakdown
- Closing balance
- Print date

Use A4-friendly formatting.

Hindi text must render correctly.

## 15. Subscription Management

Kisan Khata will operate as a subscription-based SaaS platform.

Initial requirements:

- Subscription plans
- Trial period (duration to be decided)
- Subscription start date
- Subscription expiry date
- Subscription status
- Payment history
- Renewal
- Grace period
- Suspension
- Reactivation

Plan prices, limits and payment gateway are future business decisions.

Do not hardcode subscription prices.

## 16. Language Support

Supported languages:

- Hindi
- English

Requirements:

- Language selection
- Localized interface labels
- Localized validation messages
- Localized dates and numbers where appropriate
- Hindi-compatible print output
- Searchable Hindi and English customer data

## 17. Future Android Application

Android is Phase 2.

The web application must expose versioned APIs.

Business logic must remain in backend services.

Android must not independently calculate interest or ledger balances.

Offline synchronization is not part of the initial scope.

## 18. Non-Functional Requirements

### Security

- Tenant isolation
- Role-based authorization
- Secure authentication
- Protected document uploads
- Audit trail

### Reliability

- Database transactions for financial operations
- Backup strategy
- Error logging
- Recovery procedures

### Performance

- Pagination
- Indexed searches
- Efficient ledger queries
- Avoid unnecessary database queries

### Usability

- Mobile-responsive UI
- Simple forms
- Large, readable controls
- Clear Hindi terminology
- Confirmation for sensitive financial operations

## 19. Initial Release Scope

Include:

- Authentication
- Shop registration
- Tenant isolation
- Customer management
- Credit sales
- Payments
- Customer ledger
- Interest configuration
- Interest engine
- Crop-season settlements
- Basic reports
- Ledger printing
- Subscription foundations

## 20. Excluded From Initial Release

Unless separately approved:

- Android application
- Offline synchronization
- Inventory management
- GST accounting
- Full double-entry accounting
- Payroll
- E-commerce
- Automated WhatsApp messaging
- Banking integrations

## 21. Success Criteria

The initial product should allow a shop owner to:

1. Register a shop.
2. Add a customer.
3. Record a credit sale.
4. Record a payment.
5. View the customer's complete ledger.
6. Understand outstanding principal and interest.
7. Perform an approved settlement.
8. Print the customer ledger.
9. Use the application in Hindi or English.
10. Access only their shop's records.
