# 🦷 Dental Clinic Patient Management System - Handover Guide

> **Project**: InvoiceShelf Customization for Albertine Dental Surgery  
> **Status**: Backend foundation complete, Frontend implementation pending  
> **Date**: December 22, 2025  
> **Version**: 1.0

---

## 📋 Table of Contents

1. [Context & Goal](#context--goal)
2. [Progress Summary](#progress-summary)
3. [What's Been Completed](#whats-been-completed)
4. [What Remains to Be Done](#what-remains-to-be-done)
5. [Architecture Overview](#architecture-overview)
6. [Key Design Decisions](#key-design-decisions)
7. [Implementation Checklist](#implementation-checklist)
8. [Testing Guide](#testing-guide)
9. [Known Issues & Considerations](#known-issues--considerations)
10. [Quick Start for Next Agent](#quick-start-for-next-agent)

---

## Context & Goal

### The Problem

InvoiceShelf is a generic invoicing system that needs to be transformed into a dental clinic-friendly patient management and billing system. The current system uses generic terminology ("Customers", "Items", "Estimates") and lacks dental-specific workflows.

### The Solution

Transform InvoiceShelf into a dental clinic system with:

1. **Terminology Changes** (UI only):
   - "Customers" → "Patients"
   - "Items" → "Procedures"
   - "Estimates" → "Quotations"

2. **Multi-Step Patient Wizard**:
   - Step 1: Demographics (File No., Name, Sex, Age, Address, Contact)
   - Step 2: Clinical Notes (Complaints, Diagnosis, Plan, Treatments/Procedures, Review Date)
   - Step 3: Finances (Billable Amount, Amount Paid, Balance, Payment Method)

3. **Auto-Population**:
   - Patient treatment details automatically populate into new invoices/quotations
   - Treatment plan notes auto-populate into invoice notes

4. **Custom Receipt Template**:
   - Match physical receipt book format
   - Include amount in words (using PHP's native NumberFormatter)

5. **Payment Method Display**:
   - Show payment methods on invoices

### Business Requirements

- **File Number**: Unique patient identifier (manually assigned, e.g., "ADS-001")
- **Pending Procedures**: Treatments selected during patient creation that haven't been billed yet
- **Handoff Pattern**: When invoice is created, pending procedures are auto-populated and then cleared
- **Save & Bill**: Button that saves patient and redirects to invoice creation with data pre-filled
- **Edit Mode**: Editing existing patients should hide the Finances step to prevent duplicate billing

---

## Progress Summary

### ✅ Completed (Backend Foundation - ~40%)

| Component | Status | Notes |
|-----------|--------|-------|
| **Database Schema** | ✅ Complete | Migration created but **NOT RUN** |
| **Customer Model** | ✅ Complete | Casts, helpers, scopes updated |
| **CustomerResource** | ✅ Complete | All patient fields included |
| **CustomerRequest** | ✅ Complete | Validation rules added |
| **PatientWizardController** | ✅ Complete | Store, draft, file number check |
| **API Routes** | ✅ Complete | All endpoints registered |
| **Pending Procedures Clearing** | ✅ Complete | InvoicesController & EstimatesController |
| **Patient Wizard Store** | ✅ Complete | Pinia store with state management |
| **File Number Search** | ✅ Complete | Both scopes updated |

### ⏳ Pending (Frontend & Polish - ~60%)

| Component | Status | Notes |
|-----------|--------|-------|
| **Frontend Components** | ❌ Not Started | PatientWizardModal, Step components |
| **Language Files** | ❌ Not Started | Terminology changes |
| **Auto-Population Logic** | ❌ Not Started | InvoiceCreate.vue watcher |
| **Receipt Template** | ❌ Not Started | Dental receipt PDF |
| **Payment Method Seeder** | ❌ Not Started | Seed per company |
| **Number to Words Helper** | ❌ Not Started | PHP NumberFormatter wrapper |
| **Invoice PDF Updates** | ❌ Not Started | Payment method display |
| **Migration Execution** | ❌ Not Run | Database changes pending |

---

## What's Been Completed

### 1. Database Migration

**File**: `database/migrations/2025_12_22_150000_add_dental_patient_fields_to_customers_table.php`

**Status**: ✅ Created, ❌ **NOT RUN**

**Fields Added**:
- `file_number` (string, unique per company)
- `gender` (enum: Male/Female)
- `complaints` (text)
- `treatment_plan_notes` (text)
- `pending_procedures` (JSON - for handoff pattern)
- `initial_payment_method` (string)
- `initial_invoice_id` (unsignedBigInteger)

**Action Required**: Run `php artisan migrate`

### 2. Customer Model Updates

**File**: `app/Models/Customer.php`

**Changes**:
- ✅ Added `pending_procedures` to `casts()` as `'array'`
- ✅ Added `hasPendingProcedures()` helper method
- ✅ Added `getPendingProceduresTotal()` helper method
- ✅ Added `clearPendingProcedures()` helper method
- ✅ Updated `scopeWhereDisplayName()` to include `file_number` search
- ✅ Updated `scopeWhereSearch()` to include `file_number` search

**Status**: ✅ Complete

### 3. CustomerResource Updates

**File**: `app/Http/Resources/CustomerResource.php`

**Changes**:
- ✅ Added all patient demographics fields (`file_number`, `gender`, `age`)
- ✅ Added all clinical fields (`complaints`, `diagnosis`, `treatment`, `treatment_plan_notes`, `review_date`)
- ✅ Added `pending_procedures` (JSON array)
- ✅ Added `has_pending_procedures` (computed)
- ✅ Added `pending_procedures_total` (computed)
- ✅ Added `initial_payment_method`

**Status**: ✅ Complete - **Critical for auto-population to work**

### 4. CustomerRequest Updates

**File**: `app/Http/Requests/CustomerRequest.php`

**Changes**:
- ✅ Added validation rules for all patient fields
- ✅ Added `pending_procedures` validation (array with nested rules)
- ✅ Updated `getCustomerPayload()` to include all new fields

**Status**: ✅ Complete

### 5. PatientWizardController

**File**: `app/Http/Controllers/V1/Admin/Patient/PatientWizardController.php`

**Endpoints**:
- ✅ `POST /api/v1/patients/wizard` - Create patient from wizard
- ✅ `POST /api/v1/patients/wizard/draft` - Save draft
- ✅ `GET /api/v1/patients/wizard/draft` - Get draft
- ✅ `DELETE /api/v1/patients/wizard/draft` - Clear draft
- ✅ `GET /api/v1/patients/check-file-number` - Async file number validation

**Status**: ✅ Complete

### 6. API Routes

**File**: `routes/api.php`

**Routes Added** (lines 257-267):
```php
Route::prefix('patients/wizard')->group(function () {
    Route::post('/', [PatientWizardController::class, 'store']);
    Route::post('/draft', [PatientWizardController::class, 'saveDraft']);
    Route::get('/draft', [PatientWizardController::class, 'getDraft']);
    Route::delete('/draft', [PatientWizardController::class, 'clearDraft']);
});

Route::get('/patients/check-file-number', [PatientWizardController::class, 'checkFileNumber']);
```

**Status**: ✅ Complete

### 7. Pending Procedures Clearing

**Files**:
- `app/Http/Controllers/V1/Admin/Invoice/InvoicesController.php` (lines 51-61)
- `app/Http/Controllers/V1/Admin/Estimate/EstimatesController.php` (lines 41-51)

**Logic**: After successful invoice/estimate creation, automatically clear `pending_procedures` from customer record.

**Status**: ✅ Complete

### 8. Patient Wizard Store

**File**: `resources/scripts/admin/stores/patient-wizard.js`

**Features**:
- ✅ State management for 3-step wizard
- ✅ Demographics, Clinical, Finances data structures
- ✅ File number async validation
- ✅ Draft save/load/clear
- ✅ Procedure management (add/remove/update)
- ✅ Submit patient with "Save & Bill" support

**Status**: ✅ Complete

---

## What Remains to Be Done

### Phase 1: Database & Backend Polish (Day 1)

#### 1.1 Run Migration
```bash
php artisan migrate
```

**Verify**:
```bash
php artisan tinker
>>> Schema::hasColumn('customers', 'file_number')
=> true
```

#### 1.2 Create Payment Method Seeder

**File**: `database/seeders/DentalPaymentMethodSeeder.php` (CREATE)

**Requirements**:
- Must create payment methods **per company**
- Must set `type = PaymentMethod::TYPE_GENERAL`
- Must set `active = true`
- Methods: "Cash", "MTN MoMo 0769969282", "Bank Transfer"

**Critical**: Without `company_id`, `type`, and `active`, payment methods won't appear in dropdowns!

**Reference**: See `DENTAL_CLINIC_IMPLEMENTATION_PLAN.md` lines 799-868

#### 1.3 Add Number to Words Helper

**File**: `app/helpers.php` (MODIFY)

**Function**: `numberToWords($number, $locale = 'en')` using PHP's native `NumberFormatter`

**Reference**: See `DENTAL_CLINIC_IMPLEMENTATION_PLAN.md` lines 409-446

**Verify PHP Extension**:
```bash
php -m | grep intl
```
If missing: `sudo apt install -y php8.3-intl` (already installed per chat history)

### Phase 2: Language Files (Day 1-2)

#### 2.1 Update Terminology

**File**: `lang/en.json` (MODIFY)

**Changes**:
- `navigation.customers` → "Patients"
- `navigation.items` → "Procedures"
- `navigation.estimates` → "Quotations"
- Update all `customers`, `items`, `estimates` sections
- Add new `patient_wizard` section with all labels
- Add `payment_methods` section

**Reference**: See `DENTAL_CLINIC_IMPLEMENTATION_PLAN.md` lines 1191-1298

### Phase 3: Frontend Components (Day 2-5)

#### 3.1 Main Wizard Modal

**File**: `resources/scripts/admin/components/modal-components/PatientWizardModal.vue` (CREATE)

**Features**:
- Integrate StepIndicator, StepDemographics, StepClinicalNotes, StepFinances, WizardNavigation
- Handle `isEdit` mode (limit to 2 steps)
- Handle "Save & Bill" redirect to `/admin/invoices/create?customer=X`
- Draft management on open/close

**Reference**: See `DENTAL_CLINIC_IMPLEMENTATION_PLAN.md` lines 1307-1474

#### 3.2 Step Components

**Files to Create**:
1. `resources/scripts/admin/components/patient-wizard/StepIndicator.vue`
2. `resources/scripts/admin/components/patient-wizard/StepDemographics.vue`
   - File number input with async validation on blur
   - Name, Gender, Age, Address, Contact fields
3. `resources/scripts/admin/components/patient-wizard/StepClinicalNotes.vue`
   - Complaints, Diagnosis, Plan, Review Date
   - Integrate TreatmentSelector component
4. `resources/scripts/admin/components/patient-wizard/StepFinances.vue`
   - Billable Amount (calculated), Amount Paid, Balance
   - Payment Method dropdown + Cash quick-select button
5. `resources/scripts/admin/components/patient-wizard/TreatmentSelector.vue`
   - BaseMultiselect for procedure search
   - Table with editable quantity and description
   - Total calculation
6. `resources/scripts/admin/components/patient-wizard/WizardNavigation.vue`
   - Back/Next buttons
   - Save Patient / Save & Bill buttons (on final step)
   - Hide "Save & Bill" in edit mode

**Reference**: See `DENTAL_CLINIC_IMPLEMENTATION_PLAN.md` lines 1170-1621

#### 3.3 Register Modal

**File**: `resources/scripts/stores/modal.js` (MODIFY)

**Action**: Register `PatientWizardModal` component

**Update**: All "Add Customer" buttons to open `PatientWizardModal` instead of `CustomerModal`

### Phase 4: Auto-Population Logic (Day 5-6)

#### 4.1 Invoice Create Auto-Population

**File**: `resources/scripts/admin/views/invoices/create/InvoiceCreate.vue` (MODIFY)

**Changes**:
- Add watcher on `invoiceStore.newInvoice.customer`
- If `customer.pending_procedures` exists, populate `invoiceStore.newInvoice.items`
- If `customer.treatment_plan_notes` exists, populate `invoiceStore.newInvoice.notes`
- Show success notification

**Reference**: See `DENTAL_CLINIC_IMPLEMENTATION_PLAN.md` lines 1651-1705

#### 4.2 Estimate Create Auto-Population

**File**: `resources/scripts/admin/views/estimates/create/EstimateCreate.vue` (MODIFY)

**Changes**: Same as InvoiceCreate.vue

### Phase 5: Receipt Template (Day 6-7)

#### 5.1 Create Dental Receipt Template

**File**: `resources/views/app/pdf/payment/dental-receipt.blade.php` (CREATE)

**Layout**:
- Header: Logo + Company Address
- Title Row: No. | RECEIPT (boxed) | Date
- Body:
  - "Received with thanks from: [patient name]"
  - "The sum of shillings: [amount in words and numbers]"
  - "Being payment of: [treatment/invoice]"
  - "Cash/cheque No.: [payment method]"
  - "Balance: [remaining balance]"
- Footer: Shs [amount box] | Signature line for ALBERTINE DENTAL SURGERY

**Reference**: See `DENTAL_CLINIC_IMPLEMENTATION_PLAN.md` lines 1822-2105

#### 5.2 Update Payment Model

**File**: `app/Models/Payment.php` (MODIFY)

**Action**: Configure to use `dental-receipt.blade.php` template

### Phase 6: Invoice PDF Updates (Day 7)

#### 6.1 Add Payment Method Display

**Files**: `resources/views/app/pdf/invoice/*.blade.php` (MODIFY all 3 templates)

**Logic**:
- If invoice has payments → show list of payments with methods
- If unpaid → show preferred payment method from `customer.initial_payment_method`

**Reference**: See `DENTAL_CLINIC_IMPLEMENTATION_PLAN.md` lines 1766-1815

### Phase 7: Testing & Polish (Day 8-10)

- End-to-end test: Create patient → Create invoice → Record payment → View receipt
- Test draft system (close modal unexpectedly, reopen)
- Test auto-population edge cases
- Test with existing patients (backward compatibility)
- UI polish and responsive design
- Fix any bugs found

---

## Architecture Overview

### The Handoff Pattern

**Problem**: How to pass treatment procedures from patient creation to invoice creation without creating duplicate data or sync bugs?

**Solution**: JSON column `pending_procedures` on `customers` table.

**Flow**:
1. Patient wizard saves procedures to `customers.pending_procedures` (JSON)
2. User clicks "Save & Bill" → redirects to `/admin/invoices/create?customer=X`
3. InvoiceCreate.vue watcher detects `customer.pending_procedures` → populates invoice items
4. User saves invoice → `InvoicesController::store()` clears `pending_procedures` server-side
5. Data lives in ONE place at a time: either "Pending" (JSON) OR "Billed" (Invoice Items)

**Why This Works**:
- ✅ No separate table = no sync bugs
- ✅ Server-side clearing = no orphaned data if browser crashes
- ✅ Atomic operation = data integrity guaranteed
- ✅ Simple to understand and maintain

### URL Parameter Pattern

**Important**: Use `?customer=X` (NOT `?customer_id=X`)

**Why**: Existing invoice/estimate stores already handle `route.query.customer`:
```javascript
// Already in invoice.js (line ~499)
if (route.query.customer) {
  let response = await customerStore.fetchCustomer(route.query.customer)
  this.newInvoice.customer = response.data.data
}
```

### File Number Search

**Critical**: Customer dropdown uses `scopeWhereDisplayName()`, NOT `scopeWhereSearch()`

**Solution**: Updated BOTH scopes to include `file_number`:
- `scopeWhereDisplayName()` → Used by `BaseCustomerSelectPopup.vue`
- `scopeWhereSearch()` → Used by `/admin/customers` list page

---

## Key Design Decisions

### 1. JSON Column vs Separate Table

| Approach | Decision | Rationale |
|----------|----------|-----------|
| Separate `patient_treatments` table | ❌ Rejected | Sync bugs, complex billed/unbilled logic |
| JSON column `pending_procedures` | ✅ Chosen | Simple handoff, no sync, atomic clearing |

### 2. Number to Words

| Approach | Decision | Rationale |
|----------|----------|-----------|
| Custom PHP class | ❌ Rejected | Maintenance burden, edge case bugs |
| PHP's native `NumberFormatter` | ✅ Chosen | Zero maintenance, robust, i18n ready |

### 3. "Save & Bill" UX

| Approach | Decision | Rationale |
|----------|----------|-----------|
| Checkbox "Create Invoice Now" | ❌ Rejected | Easy to forget, extra step |
| "Save & Bill" button → redirect | ✅ Chosen | Seamless UX, no forgotten invoices |

### 4. Edit Mode

| Approach | Decision | Rationale |
|----------|----------|-----------|
| Show all 3 steps in edit mode | ❌ Rejected | Risk of duplicate invoices |
| Limit to 2 steps (hide Finances) | ✅ Chosen | Prevents accidental re-billing |

### 5. Draft System

| Approach | Decision | Rationale |
|----------|----------|-----------|
| LocalStorage only | ❌ Rejected | Lost if browser cleared |
| Server-side cache + LocalStorage | ✅ Chosen | Dual backup, 24h expiry |

---

## Implementation Checklist

### ✅ Phase 1: Database & Backend Foundation
- [x] Create migration `add_dental_patient_fields_to_customers_table`
- [x] Update `Customer.php` (casts, helpers, scopes)
- [x] Update `CustomerResource.php` (all patient fields)
- [x] Update `CustomerRequest.php` (validation, payload)
- [x] Create `PatientWizardController.php`
- [x] Add API routes
- [x] Update `InvoicesController` (clear pending_procedures)
- [x] Update `EstimatesController` (clear pending_procedures)
- [x] Create `patient-wizard.js` store
- [ ] **Run migration** ⚠️
- [ ] Create `DentalPaymentMethodSeeder.php`
- [ ] Add `numberToWords()` helper function

### ⏳ Phase 2: Language Files
- [ ] Update `lang/en.json` (Customers → Patients)
- [ ] Update `lang/en.json` (Items → Procedures)
- [ ] Update `lang/en.json` (Estimates → Quotations)
- [ ] Add `patient_wizard` section
- [ ] Add `payment_methods` section

### ⏳ Phase 3: Frontend Components
- [ ] Create `PatientWizardModal.vue`
- [ ] Create `StepIndicator.vue`
- [ ] Create `StepDemographics.vue` (with async file number validation)
- [ ] Create `StepClinicalNotes.vue`
- [ ] Create `TreatmentSelector.vue` (editable description)
- [ ] Create `StepFinances.vue`
- [ ] Create `WizardNavigation.vue`
- [ ] Register modal in modal store
- [ ] Update "Add Customer" buttons

### ⏳ Phase 4: Auto-Population
- [ ] Add watcher in `InvoiceCreate.vue`
- [ ] Add `populatePendingProcedures()` helper
- [ ] Add same logic to `EstimateCreate.vue`
- [ ] Test "Save & Bill" flow

### ⏳ Phase 5: Receipt Template
- [ ] Create `dental-receipt.blade.php`
- [ ] Update `Payment.php` model
- [ ] Test PDF generation
- [ ] Fine-tune styling

### ⏳ Phase 6: Invoice PDF Updates
- [ ] Update invoice templates (payment method display)
- [ ] Test with paid/unpaid invoices

### ⏳ Phase 7: Testing & Polish
- [ ] End-to-end testing
- [ ] Draft system testing
- [ ] Auto-population edge cases
- [ ] Backward compatibility
- [ ] UI polish

---

## Testing Guide

### 1. Database Migration

```bash
# Run migration
php artisan migrate

# Verify columns exist
php artisan tinker
>>> Schema::hasColumn('customers', 'file_number')
=> true
>>> Schema::hasColumn('customers', 'pending_procedures')
=> true
```

### 2. File Number Search

```bash
# Create test patient with file number
php artisan tinker
>>> $customer = Customer::first()
>>> $customer->update(['file_number' => 'ADS-001'])

# Test search in UI:
# 1. Go to /admin/invoices/create
# 2. Click customer dropdown
# 3. Type "ADS-001"
# 4. Should find patient
```

### 3. Pending Procedures Handoff

```bash
# Create patient with pending procedures
php artisan tinker
>>> $customer = Customer::first()
>>> $customer->update(['pending_procedures' => [['item_id' => 1, 'name' => 'Root Canal', 'price' => 50000, 'quantity' => 1]]])

# Test in UI:
# 1. Go to /admin/invoices/create?customer={customer_id}
# 2. Verify invoice items auto-populate
# 3. Save invoice
# 4. Verify pending_procedures is cleared
>>> $customer->refresh()
>>> $customer->pending_procedures
=> null
```

### 4. Patient Wizard

**Test Flow**:
1. Click "Add Patient" button
2. Fill Step 1 (Demographics) → Click "Next"
3. Fill Step 2 (Clinical Notes) → Add procedures → Click "Next"
4. Fill Step 3 (Finances) → Click "Save & Bill"
5. Verify redirect to `/admin/invoices/create?customer=X`
6. Verify invoice items are pre-populated

### 5. Receipt Template

**Test Flow**:
1. Create invoice with payment
2. Record payment
3. Download receipt PDF
4. Verify layout matches physical receipt book
5. Verify amount in words is correct

---

## Known Issues & Considerations

### 1. Migration Not Run

**Status**: Migration file exists but hasn't been executed.

**Action**: Run `php artisan migrate` before starting frontend work.

### 2. Payment Method Seeder Missing

**Impact**: Payment method dropdown will be empty on fresh install.

**Action**: Create and run `DentalPaymentMethodSeeder` after migration.

**Critical**: Must include `company_id`, `type=GENERAL`, `active=true` or methods won't appear!

### 3. Frontend Components Not Created

**Status**: Store exists, but no Vue components yet.

**Action**: Create all 7 components listed in Phase 3.

### 4. Language Files Not Updated

**Impact**: UI will still show "Customers", "Items", "Estimates".

**Action**: Update `lang/en.json` as specified in Phase 2.

### 5. Auto-Population Not Implemented

**Impact**: "Save & Bill" will redirect but won't auto-populate invoice items.

**Action**: Add watcher in `InvoiceCreate.vue` as specified in Phase 4.

### 6. Receipt Template Not Created

**Impact**: Receipts will use default template, not dental-specific format.

**Action**: Create `dental-receipt.blade.php` as specified in Phase 5.

### 7. Backward Compatibility

**Status**: All new fields are nullable, so existing customers will continue to work.

**Consideration**: Existing customers won't have file numbers or clinical data. This is expected.

---

## Quick Start for Next Agent

### Step 1: Verify Environment

```bash
# Check PHP intl extension (required for number to words)
php -m | grep intl

# Check database connection
php artisan tinker
>>> DB::connection()->getPdo()
```

### Step 2: Run Migration

```bash
php artisan migrate
```

### Step 3: Review Implementation Plan

Read `DENTAL_CLINIC_IMPLEMENTATION_PLAN.md` for detailed code snippets and architecture decisions.

### Step 4: Start with Phase 1 (Backend Polish)

1. Create `DentalPaymentMethodSeeder.php`
2. Add `numberToWords()` helper
3. Run seeder: `php artisan db:seed --class=DentalPaymentMethodSeeder`

### Step 5: Move to Phase 2 (Language Files)

Update `lang/en.json` with all terminology changes.

### Step 6: Build Frontend (Phase 3-4)

Start with `PatientWizardModal.vue`, then build step components one by one.

### Step 7: Test Incrementally

After each component, test in browser to catch issues early.

### Step 8: Complete Receipt & PDF Updates (Phase 5-6)

### Step 9: Final Testing (Phase 7)

---

## File Reference Map

### Backend Files (✅ Complete)

| File | Status | Purpose |
|------|--------|---------|
| `database/migrations/2025_12_22_150000_add_dental_patient_fields_to_customers_table.php` | ✅ Created | Database schema |
| `app/Models/Customer.php` | ✅ Updated | Model with helpers & scopes |
| `app/Http/Resources/CustomerResource.php` | ✅ Updated | API response transformation |
| `app/Http/Requests/CustomerRequest.php` | ✅ Updated | Validation & payload |
| `app/Http/Controllers/V1/Admin/Patient/PatientWizardController.php` | ✅ Created | Wizard endpoints |
| `app/Http/Controllers/V1/Admin/Invoice/InvoicesController.php` | ✅ Updated | Clear pending_procedures |
| `app/Http/Controllers/V1/Admin/Estimate/EstimatesController.php` | ✅ Updated | Clear pending_procedures |
| `routes/api.php` | ✅ Updated | API routes |

### Frontend Files (✅ Store Complete, ❌ Components Missing)

| File | Status | Purpose |
|------|--------|---------|
| `resources/scripts/admin/stores/patient-wizard.js` | ✅ Complete | State management |
| `resources/scripts/admin/components/modal-components/PatientWizardModal.vue` | ❌ Missing | Main wizard modal |
| `resources/scripts/admin/components/patient-wizard/StepIndicator.vue` | ❌ Missing | Progress indicator |
| `resources/scripts/admin/components/patient-wizard/StepDemographics.vue` | ❌ Missing | Step 1 form |
| `resources/scripts/admin/components/patient-wizard/StepClinicalNotes.vue` | ❌ Missing | Step 2 form |
| `resources/scripts/admin/components/patient-wizard/TreatmentSelector.vue` | ❌ Missing | Procedure selector |
| `resources/scripts/admin/components/patient-wizard/StepFinances.vue` | ❌ Missing | Step 3 form |
| `resources/scripts/admin/components/patient-wizard/WizardNavigation.vue` | ❌ Missing | Navigation buttons |

### Files to Create/Modify

| File | Action | Priority |
|------|--------|----------|
| `database/seeders/DentalPaymentMethodSeeder.php` | CREATE | High |
| `app/helpers.php` | MODIFY | High |
| `lang/en.json` | MODIFY | High |
| `resources/views/app/pdf/payment/dental-receipt.blade.php` | CREATE | Medium |
| `resources/scripts/admin/views/invoices/create/InvoiceCreate.vue` | MODIFY | High |
| `resources/scripts/admin/views/estimates/create/EstimateCreate.vue` | MODIFY | High |
| `resources/views/app/pdf/invoice/*.blade.php` | MODIFY | Low |

---

## Important Notes

### ⚠️ Critical: Migration Not Run

The database migration exists but **has not been executed**. You must run `php artisan migrate` before any frontend work will function.

### ⚠️ Critical: CustomerResource Includes All Fields

The `CustomerResource` has been updated to include ALL patient fields including `pending_procedures`. This is **critical** for auto-population to work. If you modify the resource, ensure these fields remain.

### ⚠️ Critical: URL Parameter is `customer` Not `customer_id`

When redirecting from "Save & Bill", use `?customer=X` (not `?customer_id=X`). The existing stores expect `route.query.customer`.

### ⚠️ Critical: Payment Method Seeder Must Include Company ID

Payment methods are filtered by `company_id`, `type=GENERAL`, and `active=true`. Without these, methods won't appear in dropdowns.

### ✅ Good: Backward Compatibility Maintained

All new fields are nullable, so existing customers will continue to work without issues.

### ✅ Good: Server-Side Clearing Prevents Data Loss

`pending_procedures` is cleared server-side in the same transaction as invoice creation, preventing orphaned data if the browser crashes.

---

## Contact & Resources

### Documentation

- **Main Implementation Plan**: `DENTAL_CLINIC_IMPLEMENTATION_PLAN.md` (2300 lines, comprehensive)
- **This Handover Guide**: `HANDOVER_GUIDE.md` (this file)

### Key Code References

- **Migration**: `database/migrations/2025_12_22_150000_add_dental_patient_fields_to_customers_table.php`
- **Store**: `resources/scripts/admin/stores/patient-wizard.js`
- **Controller**: `app/Http/Controllers/V1/Admin/Patient/PatientWizardController.php`
- **Resource**: `app/Http/Resources/CustomerResource.php`

### Testing Endpoints

- `POST /api/v1/patients/wizard` - Create patient
- `GET /api/v1/patients/check-file-number?number=ADS-001` - Check file number
- `GET /api/v1/patients/wizard/draft` - Get draft
- `POST /api/v1/patients/wizard/draft` - Save draft

---

## Summary

**Current State**: Backend foundation is solid (~40% complete). Database schema designed, models updated, API endpoints created, store implemented. Frontend components and language files are pending.

**Next Steps**: 
1. Run migration
2. Create payment method seeder
3. Add number to words helper
4. Update language files
5. Build frontend components
6. Implement auto-population
7. Create receipt template
8. Test and polish

**Estimated Time**: 8-10 days for remaining work.

**Risk Level**: Low - architecture is sound, backward compatible, and well-documented.

---

*End of Handover Guide*
