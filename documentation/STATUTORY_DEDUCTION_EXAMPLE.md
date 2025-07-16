# Enhanced Statutory Deduction System

## Overview
The enhanced payroll engine now supports country-specific statutory deduction configurations where employers can choose to pay statutory deductions on behalf of employees, with proper tax handling for taxable benefits.

## Key Features

### 1. Company-Specific Configuration
- `CompanyStatutoryDeductionConfiguration` model allows companies to configure:
  - Whether employer covers employee portion
  - Whether employer-covered deductions are taxable benefits
  - Rate overrides for specific deductions
  - Effective date ranges

### 2. Enhanced Calculation Logic
- `StatutoryDeductionCalculator` now includes:
  - Company configuration lookup
  - Taxable benefit calculation for employer-paid deductions
  - PAYE recalculation with adjusted gross salary
  - Detailed breakdown of payment responsibility

### 3. API Endpoints
- `/statutory-deduction-configurations` - CRUD operations for company configurations
- `/statutory-deduction-configurations/available-templates` - Get employer-payable templates
- `/statutory-deduction-configurations/{uuid}/preview-calculation` - Test calculations

## Example Usage

### 1. Configure Company to Pay UIF on Behalf of Employees

```json
POST /statutory-deduction-configurations
{
  "statutory_deduction_template_uuid": "uif-template-uuid",
  "employer_covers_employee_portion": true,
  "is_taxable_if_employer_paid": false,
  "effective_from": "2025-01-01"
}
```

### 2. Configure Company to Pay PAYE (Taxable Benefit)

```json
POST /statutory-deduction-configurations
{
  "statutory_deduction_template_uuid": "paye-template-uuid",
  "employer_covers_employee_portion": true,
  "is_taxable_if_employer_paid": true,
  "effective_from": "2025-01-01"
}
```

### 3. Payroll Calculation Result

```json
{
  "deductions": [
    {
      "name": "UIF",
      "employee_amount": 0,
      "employer_amount": 120.00,
      "paid_by": "employer",
      "is_taxable": false
    },
    {
      "name": "PAYE",
      "employee_amount": 0,
      "employer_amount": 3500.00,
      "paid_by": "employer",
      "is_taxable": true
    }
  ],
  "taxable_benefits": [
    {
      "name": "PAYE",
      "amount": 3500.00,
      "reason": "employer_paid_deduction"
    }
  ]
}
```

## Database Schema

### statutory_deduction_templates
- Added `is_employer_payable` - indicates if employer can optionally pay
- Added `employer_covers_employee_portion` - default configuration
- Added `is_taxable_if_employer_paid` - default tax treatment
- Added `country_uuid` - country-specific configurations

### company_statutory_deduction_configurations
- Links company to specific statutory deduction templates
- Overrides default template configuration
- Includes rate overrides and effective date ranges
- Tracks who created/modified configurations

## Benefits

1. **Compliance**: Proper handling of taxable benefits for tax authorities
2. **Flexibility**: Per-company configuration of statutory deduction handling
3. **Accuracy**: Automatic PAYE recalculation when employer-paid deductions are taxable
4. **Transparency**: Clear breakdown of who pays what and tax implications
5. **Audit Trail**: Full tracking of configuration changes and effective dates

## Future Enhancements

- Age-based rebate calculations for more accurate PAYE
- Multi-currency support for international companies
- Bulk configuration import/export
- Integration with payroll processing systems
- Automated compliance reporting