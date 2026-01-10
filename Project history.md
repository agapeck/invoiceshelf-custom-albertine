# Project History - InvoiceShelf Dental Clinic Customization

> **Project**: InvoiceShelf customization for Albertine Dental Surgery  
> **Last Updated**: January 10, 2026  
> **Reference Repo**: `.temp-reference-repo` (invoiceshelf-custom-rds)

---

## Session Summary (January 10, 2026)

This session focused on wiring the PatientWizardModal, applying UI terminology changes, and implementing improvements from the reference repository.

---

## 1. PatientWizardModal Integration ✅

### Problem
The `PatientWizardModal.vue` component existed (438 lines) but was **not being triggered** - buttons still called the old `CustomerModal`.

### Files Modified
| File | Change |
|------|--------|
| `BaseCustomerSelectPopup.vue` | Changed `openCustomerModal` and `editCustomer` to use `PatientWizardModal` |
| `BaseCustomerSelectInput.vue` | Changed `addCustomer` to use `PatientWizardModal` |
| `RecurringInvoiceCreate.vue` | Changed `editCustomer` to use `PatientWizardModal` |
| `customers/Index.vue` | Added `openPatientWizard()` function, changed New Customer buttons |

### Finances Step Fix
The PatientWizardModal's Step 3 (Finances) had broken dropdowns:
- **Problem**: Used non-existent `globalStore.fetchItems()`
- **Solution**: Changed to `itemStore.fetchItems()` and `paymentStore.paymentModes`

```javascript
// Before (broken)
globalStore.fetchItems()

// After (fixed)
import { useItemStore } from '@/scripts/admin/stores/item'
import { usePaymentStore } from '@/scripts/admin/stores/payment'
const itemStore = useItemStore()
const paymentStore = usePaymentStore()
await itemStore.fetchItems({ limit: 'all' })
await paymentStore.fetchPaymentModes()
```

---

## 2. UI Terminology Renames ✅

### lang/en.json Changes
| Original | New |
|----------|-----|
| `navigation.customers` | "Patients" |
| `navigation.items` | "Procedures" |
| `navigation.estimates` | "Quotations" |
| `customers.title` | "Patients" |
| `items.title` | "Procedures" |
| `estimates.title` | "Quotations" |
| `dashboard.cards.customers` | "Patient \| Patients" |
| `dashboard.cards.estimates` | "Quotation \| Quotations" |

---

## 3. Reference Repo Improvements ✅

### 3.1 Idle Logout (Already Existed)
- File: `composables/useIdleLogout.js`
- 30-minute timeout with 1-minute warning
- Already wired in `admin/layouts/LayoutBasic.vue`

### 3.2 Appointment Type Fixes
**File**: `admin/views/appointments/Index.vue`
- Fixed null/empty type display: `row.data.type ? $t(...) : '-'`
- Added 10 dental appointment types:
  - cleaning, filling, extraction, root_canal
  - crown_bridge, denture, whitening, pediatric, ortho_consult

### 3.3 General Translations
Added to `lang/en.json`:
```json
"metadata": "Metadata",
"created_at": "Created At",
"updated_at": "Updated At",
"created_by": "Created By"
```

### 3.4 Cloudflare R2 Backup Support
**New File**: `admin/components/modal-components/disks/R2Disk.vue`

**Modified Files**:
| File | Change |
|------|--------|
| `stores/disk.js` | Added `r2DiskConfigData` state |
| `FileDiskModal.vue` | Registered R2 component |
| `DiskController.php` | Added R2 case + fixed s3compat break bug |
| `lang/en.json` | Added R2 field translations |

---

## 4. Database & Server Setup

### Migration Applied
```bash
php artisan migrate
# Applied: 2025_12_22_150000_add_dental_patient_fields_to_customers_table
```

### .env Configuration
```env
APP_URL=http://localhost:9000
SESSION_DOMAIN=localhost
SANCTUM_STATEFUL_DOMAIN=localhost:9000
```

### Cache Fix
Created missing directory for file-based rate limiting:
```bash
mkdir -p storage/framework/cache/data
```

---

## 5. Existing Data Verified

### Dental Procedures (Items)
- RCT COMPLETE, EXTRACTION, SURGICAL EXTRACTION
- 1ST RCT, 2ND RCT, 3RD RCT
- PULPECTOMY, PULPOTOMY, PF (AMALGAM)
- BLEACHING, X-RAY, CONSULTATION

### Payment Methods
- Cash, Check, Credit Card
- Bank Transfer, MTN Mobile Money, Airtel Money

---

## 6. What's Still Working from Before

These existed before this session and are functional:
- `PatientWizardController.php` (backend)
- `patient-wizard.js` (Pinia store)
- `dental-receipt.blade.php` (PDF template)
- `numberToWordsWithCurrency` helper
- `patient_wizard` translations
- Pending procedures clearing in `InvoicesController`

---

## 7. The Handoff Pattern

### Flow
1. PatientWizardModal saves procedures to `customers.pending_procedures` (JSON)
2. "Save & Bill" redirects to `/admin/invoices/create?customer=X`
3. InvoiceCreate.vue detects `pending_procedures` → populates invoice items
4. Invoice save → `InvoicesController::store()` clears `pending_procedures`

### Key Implementation Points
- URL parameter is `?customer=X` (NOT `?customer_id=X`)
- Server-side clearing prevents orphaned data
- All new fields are nullable (backward compatible)

---

## 8. File Reference

### Key Frontend Files
| Purpose | Path |
|---------|------|
| Patient Wizard Modal | `admin/components/modal-components/PatientWizardModal.vue` |
| Patient Wizard Store | `admin/stores/patient-wizard.js` |
| Customers Index | `admin/views/customers/Index.vue` |
| Appointments Index | `admin/views/appointments/Index.vue` |
| Idle Logout | `composables/useIdleLogout.js` |
| R2 Disk | `admin/components/modal-components/disks/R2Disk.vue` |

### Key Backend Files
| Purpose | Path |
|---------|------|
| Patient Wizard Controller | `app/Http/Controllers/V1/Admin/Patient/PatientWizardController.php` |
| Customer Model | `app/Models/Customer.php` |
| Invoices Controller | `app/Http/Controllers/V1/Admin/Invoice/InvoicesController.php` |
| Disk Controller | `app/Http/Controllers/V1/Admin/Settings/DiskController.php` |
| Dental Receipt | `resources/views/app/pdf/payment/dental-receipt.blade.php` |

---

## 9. Commands

```bash
# Start dev server
php artisan serve --port=9000

# Rebuild frontend
npm run build

# Clear caches
php artisan config:clear
php artisan cache:clear

# Check migration status
php artisan migrate:status
```

---

## 10. Known Issues / Future Work

- [ ] Test auto-populate from pending_procedures when creating invoice
- [ ] Verify dental-receipt.blade.php PDF generation
- [ ] R2 backup validation rules in `DiskEnvironmentRequest.php` (may need adding)
- [ ] Consider R2 disk configuration in `config/filesystems.php`

---

## 11. Reference Repository Commits Applied

| Commit | Description |
|--------|-------------|
| `f03a50b` | Idle logout (already existed) |
| `bb540da` | Appointment type display fix |
| `c7a22f7` | Cloudflare R2 backup support |
| `355b694` | Soft-delete due amount fix (not applied - check if needed) |
