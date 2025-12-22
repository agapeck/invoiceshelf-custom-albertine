# 🦷 Dental Clinic Patient Management System - Implementation Plan

> **InvoiceShelf Customization for Albertine Dental Surgery**
> 
> Transform InvoiceShelf from a generic invoicing system into a dental clinic-friendly patient management and billing system.

---

## 🔄 Architecture Improvements (v1.1)

*Based on expert system architecture review*

| Original Approach | Improved Approach | Benefit |
|-------------------|-------------------|---------|
| Separate `patient_treatments` table with `is_billed` flag | **JSON column** `pending_procedures` | No sync bugs, simpler data model |
| Custom `NumberToWords` PHP class | **Native** `NumberFormatter` | Zero maintenance, robust, i18n ready |
| "Create Invoice Now" checkbox | **"Save & Bill"** button → redirect | Seamless UX, no forgotten invoices |
| Watcher + confirmation dialog | **URL param** triggers auto-populate | Automatic, cleaner code |

**Code Reduction:** ~30% fewer lines, eliminated biggest source of potential bugs (state synchronization)

---

## 🔧 Technical Refinements (v1.2)

*Addressing functional gaps for real-world dental clinic use*

| # | Refinement | Problem | Solution |
|---|------------|---------|----------|
| 1 | **File Number Search** | `file_number` invisible to search bar | Add to `scopeWhereDisplayName()` (where popup actually searches) |
| 2 | **Ad-hoc Procedure Notes** | Can't add "Tooth 42" context to procedures | Editable **description** field (keep name stable) |
| 3 | **Review Date on Invoice** | Collected but not shown to patient | ✅ **ALREADY DONE** - templates have `customer_review_date` |
| 4 | **Hide Finance in Edit Mode** | Editing patient shows billing step (risk of duplicates) | Limit wizard to 2 steps when `isEdit=true` |
| 5 | **Duplicate File Number Check** | Manual entry allows collisions | Async validation on blur |
| 6 | **Payment Method Seeder** | Dropdown empty on fresh install | Seed **per company** with `company_id`, `type=GENERAL`, `active=true` |

---

## ⚠️ Codebase Alignment Fixes (v1.3)

*Critical corrections based on actual InvoiceShelf implementation*

| Issue | Plan Said | Codebase Reality | Fix |
|-------|-----------|------------------|-----|
| **Save & Bill URL param** | `?customer_id=X` | Stores use `route.query.customer` | Use `?customer=X` |
| **File number search scope** | `scopeWhereSearch()` | Popup uses `display_name` → `scopeWhereDisplayName()` | Update `scopeWhereDisplayName()` instead |
| **Payment method seeder** | Simple `firstOrCreate` | Filtered by `whereCompany()` + `type=GENERAL` | Must include `company_id`, `type`, `active` |
| **Review date on invoice** | "Add to PDF template" | Already in all 3 templates + snapshotted in `InvoicesRequest` | Skip - already done |
| **Clear pending_procedures** | Client-side after save | Risk of stale data if client crashes | Clear **server-side** in invoice creation |
| **CustomerResource fields** | Assumed complete | Missing patient fields entirely | Must add all new fields |
| **Invoice payment method** | "Show on invoice" | Invoices can have multiple payments | Define clear rule |

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Desired Changes Overview](#desired-changes-overview)
3. [Database Schema](#database-schema)
4. [Backend Architecture](#backend-architecture)
5. [Frontend Architecture](#frontend-architecture)
6. [Language/Terminology Changes](#languageterminology-changes)
7. [Patient Wizard Modal](#patient-wizard-modal)
8. [Auto-Population Logic](#auto-population-logic)
9. [Receipt Template](#receipt-template)
10. [Implementation Checklist](#implementation-checklist)
11. [Files to Modify](#files-to-modify)

---

## Executive Summary

### Goals

1. **Rename terminology** (UI only - no database table renames):
   - Customers → Patients
   - Items → Procedures
   - Estimates → Quotations

2. **Multi-step Patient Wizard** replacing scrollable customer modal:
   - Step 1: Demographics (File No., Name, Sex, Age, Address, Contact)
   - Step 2: Clinical Notes (Complaints, Diagnosis, Plan, Treatments/Procedures, Review Date)
   - Step 3: Finances (Billable Amount, Amount Paid, Balance, Payment Method)

3. **Auto-population** of patient data into invoices/quotations/receipts

4. **Custom Receipt Template** matching physical receipt book format

5. **Payment method visibility** on invoices

### Key Design Decisions

| Decision | Approach | Rationale |
|----------|----------|-----------|
| Finance data storage | Use proper Invoice/Payment tables | Maintains accounting integrity |
| Treatment storage | JSON column `pending_procedures` | Simple handoff pattern - no sync bugs |
| Invoice creation | "Save & Bill" button redirects to invoice | Seamless UX, no forgotten invoices |
| Draft system | LocalStorage + Backend sync | Prevent data loss |
| Auto-population | URL param triggers population | Automatic, no user confirmation needed |
| Number to words | PHP's native `NumberFormatter` | Zero maintenance, robust, i18n ready |

---

## Desired Changes Overview

### From User Requirements

1. **On adding new Customer (renamed to Patient):**
   - One modal at a time (step-based wizard, not scrolling)
   - Back and Next buttons to navigate
   - Next button saves progress even before final save
   - Save Patient button on final step

2. **New Patient Fields:**

   **Modal 1 - Demographics:**
   - File No. (unique ID, manually assigned)
   - Patient's Name
   - Sex (dropdown: Male/Female)
   - Age
   - Address
   - Contact

   **Modal 2 - Clinical Notes:**
   - Complaints
   - Diagnosis
   - Plan (treatment plan notes)
   - Treatment (mapped to Items/Procedures - billable invoice items)
   - Review Date

   **Modal 3 - Finances:**
   - Billable Amount (calculated from treatments)
   - Amount Paid
   - Balance (calculated)
   - Payment Method (dropdown + Cash quick-select button)
     - Options: Cash, MTN MoMo 0769969282, Bank [details]

3. **Auto-pull into Invoice/Quotation:**
   - Treatment details as billable invoice items
   - Plan info into notes
   - All relevant patient info

4. **Receipt Template (matching physical receipt book):**
   - No. [receipt number]
   - RECEIPT [boxed]
   - Date: [date]
   - Received with thanks from: [patient name]
   - The sum of shillings: [amount in words and numbers]
   - Being payment of: [treatment/invoice description]
   - Cash/cheque No.: [payment method]
   - Balance: [remaining balance]
   - Shs [amount box]
   - Signature line for ALBERTINE DENTAL SURGERY

5. **Sidebar Menu Renames:**
   - Items → Procedures

---

## Database Schema

### Migration: Extended Patient Fields with JSON Pending Procedures

> **Architecture Note**: Instead of a separate `patient_treatments` table, we use a JSON column 
> `pending_procedures` for the "handoff" pattern. This eliminates synchronization bugs and 
> the complexity of tracking billed/unbilled state across multiple tables.

```php
<?php
// database/migrations/YYYY_MM_DD_HHMMSS_add_dental_patient_fields_to_customers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Demographics
            $table->string('file_number', 50)->nullable()->after('id');
            $table->enum('gender', ['Male', 'Female'])->nullable()->after('name');
            
            // Clinical (complaints, treatment_plan_notes - diagnosis, treatment, review_date already exist)
            $table->text('complaints')->nullable()->after('diagnosis');
            $table->text('treatment_plan_notes')->nullable()->after('treatment');
            
            // Pending procedures - JSON handoff pattern
            // Structure: [{ item_id, name, description, price, quantity }]
            // Cleared when invoice is created
            $table->json('pending_procedures')->nullable();
            
            // Reference to initial invoice (for tracking)
            $table->string('initial_payment_method')->nullable();
            $table->unsignedBigInteger('initial_invoice_id')->nullable();
            
            // Indexes
            $table->unique(['file_number', 'company_id'], 'customers_file_number_company_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_file_number_company_unique');
            $table->dropColumn([
                'file_number',
                'gender',
                'complaints',
                'treatment_plan_notes',
                'pending_procedures',
                'initial_payment_method',
                'initial_invoice_id',
            ]);
        });
    }
};
```

### Why JSON Instead of a Separate Table?

| Approach | Pros | Cons |
|----------|------|------|
| **Separate Table** | Normalized, queryable | Sync bugs, complex billed/unbilled logic |
| **JSON Column** ✅ | Simple handoff, no sync | Not independently queryable |

**The Handoff Pattern:**
1. Wizard saves procedures to `customers.pending_procedures` (JSON)
2. Invoice creation reads JSON → populates invoice items → **clears JSON**
3. Data lives in ONE place at a time: either "Pending" (JSON) OR "Billed" (Invoice Item)
4. No boolean flags, no synchronization, no bugs

### Existing Fields (Already in customers table)

From migration `2025_11_15_190342_add_patient_fields_to_customers_table.php`:
- `age` (integer, nullable) ✅ **Used in Demographics**
- `next_of_kin` (string, nullable) ⏸️ *Not used in wizard (can be added later if needed)*
- `next_of_kin_phone` (string, nullable) ⏸️ *Not used in wizard*
- `diagnosis` (text, nullable) ✅ **Used in Clinical Notes**
- `treatment` (text, nullable) ✅ **Used in Clinical Notes**
- `attended_to_by` (string, nullable) ⏸️ *Not used in wizard*
- `review_date` (date, nullable) ✅ **Used in Clinical Notes + Invoice PDF**

---

## Backend Architecture

### New Files to Create

| File | Purpose |
|------|---------|
| `app/Http/Controllers/V1/Admin/Patient/PatientWizardController.php` | Handle wizard save/draft |
| `app/Http/Requests/PatientWizardRequest.php` | Validation for wizard |

> **Note**: We removed `PatientTreatment` model and `NumberToWords` class per architecture review.
> - Treatments use JSON column `pending_procedures` (simpler, no sync bugs)
> - Number to words uses PHP's native `NumberFormatter` (zero maintenance)

### Update Customer Model

```php
// Add to app/Models/Customer.php

// Add to $fillable or update $guarded
protected $fillable = [
    // ... existing fields ...
    'file_number',
    'gender',
    'complaints',
    'treatment_plan_notes',
    'pending_procedures',  // JSON column for handoff pattern
    'initial_payment_method',
    'initial_invoice_id',
];

// Add to casts
protected function casts(): array
{
    return [
        // ... existing casts ...
        'pending_procedures' => 'array',  // Auto JSON encode/decode
    ];
}

// Helper methods for pending procedures
public function hasPendingProcedures(): bool
{
    return !empty($this->pending_procedures);
}

public function getPendingProceduresTotal(): int
{
    if (!$this->pending_procedures) {
        return 0;
    }
    
    return collect($this->pending_procedures)->sum(function ($proc) {
        return ($proc['price'] ?? 0) * ($proc['quantity'] ?? 1);
    });
}

public function clearPendingProcedures(): void
{
    $this->update(['pending_procedures' => null]);
}
```

### Update CustomerRequest.php

```php
// Add to app/Http/Requests/CustomerRequest.php rules()

'file_number' => [
    'nullable',
    'string',
    'max:50',
    Rule::unique('customers')
        ->where('company_id', $this->header('company'))
        ->ignore($this->route('customer')?->id),
],
'gender' => ['nullable', 'string', 'in:Male,Female'],
'complaints' => ['nullable', 'string', 'max:65000'],
'treatment_plan_notes' => ['nullable', 'string', 'max:65000'],

// JSON pending procedures (validated as array, stored as JSON)
'pending_procedures' => ['nullable', 'array'],
'pending_procedures.*.item_id' => ['required_with:pending_procedures', 'exists:items,id'],
'pending_procedures.*.name' => ['required_with:pending_procedures', 'string'],
'pending_procedures.*.quantity' => ['required_with:pending_procedures', 'integer', 'min:1'],
'pending_procedures.*.price' => ['required_with:pending_procedures', 'integer', 'min:0'],

// Update getCustomerPayload() to include new fields
public function getCustomerPayload()
{
    return collect($this->validated())
        ->only([
            'name',
            'email',
            'currency_id',
            'password',
            'phone',
            'prefix',
            'tax_id',
            'company_name',
            'contact_name',
            'website',
            'enable_portal',
            'estimate_prefix',
            'payment_prefix',
            'invoice_prefix',
            // New fields
            'file_number',
            'gender',
            'age',
            'complaints',
            'diagnosis',
            'treatment',
            'treatment_plan_notes',
            'pending_procedures',  // JSON array
            'review_date',
            'initial_payment_method',
        ])
        ->merge([
            'creator_id' => $this->user()->id,
            'company_id' => $this->header('company'),
        ])
        ->toArray();
}
```

### Update CustomerResource.php

**Critical**: The existing `CustomerResource.php` doesn't include ANY patient fields!
Auto-population won't work until these are added.

```php
// Add to app/Http/Resources/CustomerResource.php toArray()
// Add AFTER the existing fields (around line 53, before the closing bracket)

// Patient demographics
'file_number' => $this->file_number,
'gender' => $this->gender,
'age' => $this->age,

// Clinical notes
'complaints' => $this->complaints,
'diagnosis' => $this->diagnosis,
'treatment' => $this->treatment,
'treatment_plan_notes' => $this->treatment_plan_notes,
'review_date' => $this->review_date,
'formatted_review_date' => $this->formattedReviewDate,

// Pending procedures (JSON - for auto-population into invoices)
'pending_procedures' => $this->pending_procedures,
'has_pending_procedures' => $this->hasPendingProcedures(),
'pending_procedures_total' => $this->getPendingProceduresTotal(),

// Payment tracking
'initial_payment_method' => $this->initial_payment_method,
```

**Why this is critical:**
- `BaseCustomerSelectPopup.vue` and invoice stores fetch customer via `/api/v1/customers/:id`
- This returns data from `CustomerResource`
- Without these fields, `pending_procedures` will be `undefined` in the frontend
- Auto-population logic will silently fail

### Number to Words (Using Native PHP NumberFormatter)

> **Architecture Note**: Instead of a custom class, we use PHP's built-in `NumberFormatter`.
> This is zero-maintenance, robust, handles edge cases, and supports internationalization.

```php
<?php
// Helper function - add to app/helpers.php or use inline

/**
 * Convert number to words using PHP's native NumberFormatter
 * 
 * @param int|float $number
 * @param string $locale
 * @return string
 */
function numberToWords(int|float $number, string $locale = 'en'): string
{
    $formatter = new \NumberFormatter($locale, \NumberFormatter::SPELLOUT);
    return $formatter->format((int) $number);
}

/**
 * Convert number to words with currency suffix
 * 
 * @param int|float $number
 * @param string $currency
 * @return string
 */
function numberToWordsWithCurrency(int|float $number, string $currency = 'shillings'): string
{
    return ucfirst(numberToWords($number)) . ' ' . $currency . ' only';
}

// Usage examples:
// numberToWords(1250) => "one thousand two hundred fifty"
// numberToWordsWithCurrency(50000) => "Fifty thousand shillings only"
```

**Why Native NumberFormatter?**
- ✅ Built into PHP (requires `intl` extension - already installed)
- ✅ Handles edge cases (large numbers, negatives)
- ✅ Supports 100+ languages/locales
- ✅ Zero maintenance code
- ❌ Custom class = bugs, maintenance, incomplete edge case handling

### API Routes

```php
// Add to routes/api.php

// Patient Wizard Routes (simplified - no separate treatments controller needed)
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // Patient Wizard
    Route::post('patients/wizard', [PatientWizardController::class, 'store']);
    Route::post('patients/wizard/draft', [PatientWizardController::class, 'saveDraft']);
    Route::get('patients/wizard/draft', [PatientWizardController::class, 'getDraft']);
    Route::delete('patients/wizard/draft', [PatientWizardController::class, 'clearDraft']);
    
    // Check file number availability (for async validation)
    Route::get('patients/check-file-number', [PatientWizardController::class, 'checkFileNumber']);
});
```

> **Note**: `pending_procedures` is cleared **server-side** in `InvoicesController::store()` 
> and `EstimatesController::store()` - no separate endpoint needed.

---

## Technical Refinements (Detailed Implementation)

### Refinement 1: Enable File Number Search

**Problem**: Receptionists can't find patients by file number (e.g., "ADS-001") using the search bar.

**Critical**: The customer dropdown (`BaseCustomerSelectPopup.vue`) uses `display_name` param, which maps to 
`scopeWhereDisplayName()`, NOT `scopeWhereSearch()`. So we must update BOTH scopes.

```php
// Modify app/Models/Customer.php

// PRIMARY FIX: This is what the customer dropdown popup actually uses
public function scopeWhereDisplayName($query, $displayName)
{
    return $query->where(function ($query) use ($displayName) {
        $query->where('name', 'LIKE', '%' . $displayName . '%')
            ->orWhere('file_number', 'LIKE', '%' . $displayName . '%');  // ADD THIS
    });
}

// ALSO UPDATE: This is used by the customers list page search
public function scopeWhereSearch($query, $search)
{
    foreach (explode(' ', $search) as $term) {
        $query->where(function ($query) use ($term) {
            $query->where('name', 'LIKE', '%' . $term . '%')
                ->orWhere('email', 'LIKE', '%' . $term . '%')
                ->orWhere('phone', 'LIKE', '%' . $term . '%')
                ->orWhere('file_number', 'LIKE', '%' . $term . '%');  // ADD THIS
        });
    }
}
```

**Why both?**
- `scopeWhereDisplayName()` → Used by `BaseCustomerSelectPopup.vue` (dropdown when creating invoices)
- `scopeWhereSearch()` → Used by `/admin/customers` list page

### Refinement 2: Ad-hoc Procedure Editing

**Problem**: Selecting "Root Canal" locks description, but dentists need to add context like "Tooth 42, extra anaesthetic".

**Architecture Decision**: Keep procedure **name** stable (from item catalog); make **description** editable for clinical notes.
This ensures reports/analytics remain consistent while allowing per-patient context.

```vue
<!-- In TreatmentSelector.vue - make DESCRIPTION editable, keep name read-only -->

<tbody class="divide-y divide-gray-200">
  <tr v-for="(treatment, index) in treatments" :key="treatment.item_id">
    <td class="px-4 py-3">
      <!-- Name is READ-ONLY (from item catalog) -->
      <div class="font-medium text-gray-900">{{ treatment.name }}</div>
      
      <!-- Description is EDITABLE (for clinical context) -->
      <BaseInput
        v-model="treatment.description"
        class="text-sm text-gray-500 mt-1"
        placeholder="Add clinical notes (e.g., Tooth 42, extra anaesthetic)"
      />
    </td>
    <!-- ... rest of columns ... -->
  </tr>
</tbody>
```

**Store update** - description is editable, name stays from catalog:

```javascript
// In patient-wizard.js - wizardPayload getter
pending_procedures: state.clinical.treatments.map(t => ({
  item_id: t.item_id,
  name: t.name,  // FROM CATALOG (not editable) - ensures consistent reporting
  description: t.description,  // USER-EDITABLE clinical context (e.g., "Tooth 42")
  quantity: t.quantity || 1,
  price: t.price,
})),
```

### Refinement 3: Print Review Date on Invoices

**✅ ALREADY IMPLEMENTED** - No action needed!

The codebase already has this feature:

1. **Invoice templates** (`invoice1/2/3.blade.php`) already render review date:
```blade
{{-- Already exists in all 3 invoice templates --}}
@if ($invoice->customer_age || ... || $invoice->customer_review_date)
    <div class="patient-info-label">@lang('pdf_review_date')</div>
    <div class="patient-info-value">{{ $invoice->customer_review_date ? ... }}</div>
@endif
```

2. **InvoicesRequest.php** already snapshots patient data including review date:
```php
// Already in getInvoicePayload()
'customer_review_date' => $customer->review_date,
```

3. **Migration** already added `customer_review_date` to invoices table:
```php
// 2025_11_15_190401_add_patient_snapshot_to_invoices_table.php
$table->date('customer_review_date')->nullable();
```

**Skip this refinement during implementation.**

### Refinement 4: Hide Finance Step in Edit Mode

**Problem**: Opening patient to fix typo shows billing step, risking duplicate invoices.

```vue
<!-- In PatientWizardModal.vue -->

<script setup>
// ... existing imports ...

const isEdit = computed(() => modalStore.data?.isEdit || false)

// Dynamic step count based on mode
const maxSteps = computed(() => isEdit.value ? 2 : 3)
const stepTitles = computed(() => {
  const titles = ['Demographics', 'Clinical Notes']
  if (!isEdit.value) {
    titles.push('Finances')
  }
  return titles
})
</script>

<template>
  <!-- Step Indicator uses dynamic maxSteps -->
  <StepIndicator
    :current-step="wizardStore.currentStep"
    :total-steps="maxSteps"
    :step-titles="stepTitles"
    class="px-6 pt-4"
  />

  <!-- Step Content -->
  <div class="px-6 py-4 min-h-[400px]">
    <StepDemographics v-show="wizardStore.currentStep === 1" :v="v$" />
    <StepClinicalNotes v-show="wizardStore.currentStep === 2" />
    <!-- Only show Finances in create mode -->
    <StepFinances v-if="!isEdit" v-show="wizardStore.currentStep === 3" />
  </div>

  <!-- Navigation adapts to edit mode -->
  <WizardNavigation
    :is-saving="wizardStore.isSaving"
    :is-edit="isEdit"
    :max-steps="maxSteps"
    :has-procedures="wizardStore.clinical.treatments.length > 0"
    @back="wizardStore.prevStep"
    @next="handleNext"
    @save-only="handleSaveOnly"
    @save-and-bill="handleSaveAndBill"
  />
</template>
```

```vue
<!-- In WizardNavigation.vue -->

<template>
  <div class="px-6 py-4 bg-gray-50 border-t flex justify-between">
    <BaseButton
      v-if="currentStep > 1"
      variant="secondary"
      @click="$emit('back')"
    >
      {{ $t('patient_wizard.back') }}
    </BaseButton>
    <div v-else></div>
    
    <div class="flex gap-3">
      <!-- On final step: show save buttons -->
      <template v-if="currentStep === maxSteps">
        <BaseButton
          variant="secondary"
          :loading="isSaving"
          @click="$emit('save-only')"
        >
          {{ $t('patient_wizard.save_patient') }}
        </BaseButton>
        
        <!-- Only show "Save & Bill" in create mode with procedures -->
        <BaseButton
          v-if="!isEdit && hasProcedures"
          variant="primary"
          :loading="isSaving"
          @click="$emit('save-and-bill')"
        >
          {{ $t('patient_wizard.save_and_bill') }}
        </BaseButton>
      </template>
      
      <!-- Not on final step: show Next -->
      <BaseButton
        v-else
        variant="primary"
        @click="$emit('next')"
      >
        {{ $t('patient_wizard.next') }}
      </BaseButton>
    </div>
  </div>
</template>

<script setup>
defineProps({
  currentStep: { type: Number, required: true },
  maxSteps: { type: Number, required: true },
  isSaving: { type: Boolean, default: false },
  isEdit: { type: Boolean, default: false },
  hasProcedures: { type: Boolean, default: false },
})
</script>
```

### Refinement 5: Prevent Duplicate File Numbers (Async Validation)

**Problem**: Manual entry of file numbers allows collision errors.

**Backend endpoint:**

```php
// Add to PatientWizardController.php

public function checkFileNumber(Request $request)
{
    $request->validate(['number' => 'required|string']);
    
    $exists = Customer::where('company_id', $request->header('company'))
        ->where('file_number', $request->number)
        ->when($request->exclude_id, function ($query) use ($request) {
            return $query->where('id', '!=', $request->exclude_id);
        })
        ->exists();
    
    return response()->json(['exists' => $exists]);
}
```

**Frontend validation:**

```vue
<!-- In StepDemographics.vue -->

<template>
  <div class="grid grid-cols-2 gap-4">
    <BaseInputGroup
      :label="$t('patient_wizard.file_number')"
      :error="fileNumberError || v$.demographics.file_number.$errors[0]?.$message"
      required
    >
      <BaseInput
        v-model="wizardStore.demographics.file_number"
        :placeholder="$t('patient_wizard.file_number_placeholder')"
        :class="{ 'border-red-500': fileNumberError }"
        @blur="checkFileNumberAvailability"
      />
      <span v-if="isCheckingFileNumber" class="text-sm text-gray-400">
        Checking...
      </span>
    </BaseInputGroup>
    <!-- ... other fields ... -->
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { usePatientWizardStore } from '@/scripts/admin/stores/patient-wizard'
import { useModalStore } from '@/scripts/stores/modal'

const wizardStore = usePatientWizardStore()
const modalStore = useModalStore()

const fileNumberError = ref(null)
const isCheckingFileNumber = ref(false)

async function checkFileNumberAvailability() {
  const fileNumber = wizardStore.demographics.file_number
  if (!fileNumber || fileNumber.length < 2) {
    fileNumberError.value = null
    return
  }
  
  isCheckingFileNumber.value = true
  fileNumberError.value = null
  
  try {
    const { data } = await axios.get('/api/v1/patients/check-file-number', {
      params: {
        number: fileNumber,
        exclude_id: modalStore.data?.customer?.id || null, // Exclude current patient in edit mode
      }
    })
    
    if (data.exists) {
      fileNumberError.value = 'This file number is already in use'
    }
  } catch (error) {
    console.error('Failed to check file number:', error)
  } finally {
    isCheckingFileNumber.value = false
  }
}
</script>
```

### Refinement 6: Seed Essential Payment Methods

**Problem**: Payment method dropdown is empty on fresh install.

**Critical**: Payment methods are filtered by `->whereCompany()` and `->where('type', GENERAL)` in the controller.
Seeding without `company_id` and `type` means methods won't appear in dropdowns!

```php
<?php
// database/seeders/DentalPaymentMethodSeeder.php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class DentalPaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $paymentMethods = [
            [
                'name' => 'Cash',
                'driver' => 'cash',
            ],
            [
                'name' => 'MTN MoMo 0769969282',
                'driver' => 'mobile_money',
            ],
            [
                'name' => 'Bank Transfer',
                'driver' => 'bank_transfer',
            ],
        ];

        // CRITICAL: Must create per company, with type and active fields
        $companies = Company::all();
        
        foreach ($companies as $company) {
            foreach ($paymentMethods as $method) {
                PaymentMethod::firstOrCreate(
                    [
                        'name' => $method['name'],
                        'company_id' => $company->id,  // REQUIRED
                    ],
                    [
                        'name' => $method['name'],
                        'driver' => $method['driver'],
                        'company_id' => $company->id,
                        'type' => PaymentMethod::TYPE_GENERAL,  // REQUIRED - controller filters by this
                        'active' => true,  // Show in dropdowns
                    ]
                );
            }
            
            $this->command->info("Seeded payment methods for company: {$company->name}");
        }
        
        $this->command->info('Dental payment methods seeded successfully!');
    }
}
```

```php
// Update database/seeders/DatabaseSeeder.php

public function run(): void
{
    // ... existing seeders ...
    
    $this->call([
        // ... existing calls ...
        DentalPaymentMethodSeeder::class,
    ]);
}
```

**Run after deployment:**
```bash
php artisan db:seed --class=DentalPaymentMethodSeeder
```

**Why this matters:**
- `PaymentMethodsController::index()` filters with `->whereCompany()` and `->where('type', TYPE_GENERAL)`
- Without `company_id`, methods won't appear in any company's dropdown
- Without `type = GENERAL`, methods are treated as module payment methods (Stripe, PayPal, etc.)

---

## Frontend Architecture

### New Store: patient-wizard.js

```javascript
// resources/scripts/admin/stores/patient-wizard.js

import axios from 'axios'
import { defineStore } from 'pinia'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'

export const usePatientWizardStore = defineStore('patient-wizard', {
  state: () => ({
    // Step tracking
    currentStep: 1,
    totalSteps: 3,
    stepTitles: ['Demographics', 'Clinical Notes', 'Finances'],
    
    // Step 1: Demographics
    demographics: {
      file_number: '',
      name: '',
      gender: null,
      age: null,
      phone: '',
      billing: {
        address_street_1: '',
        city: '',
        state: '',
        country_id: null,
        zip: '',
      },
    },
    
    // Step 2: Clinical Notes
    clinical: {
      complaints: '',
      diagnosis: '',
      treatment_plan_notes: '',
      treatments: [], // Array of { item_id, name, description, price, quantity }
      review_date: null,
    },
    
    // Step 3: Finances
    finances: {
      amount_paid: 0,
      payment_method_id: null,
    },
    
    // Save action (determined by button clicked)
    saveAction: 'save_only', // 'save_only' | 'save_and_bill'
    
    // Additional customer fields
    currency_id: null,
    
    // UI State
    isDraft: false,
    isLoading: false,
    isSaving: false,
  }),
  
  getters: {
    billableAmount: (state) => {
      return state.clinical.treatments.reduce(
        (sum, t) => sum + (t.price * (t.quantity || 1)), 
        0
      )
    },
    
    balance: (state) => {
      const billable = state.clinical.treatments.reduce(
        (sum, t) => sum + (t.price * (t.quantity || 1)), 
        0
      )
      return billable - (state.finances.amount_paid || 0)
    },
    
    canProceedToStep2: (state) => {
      return state.demographics.name && state.demographics.name.length >= 2
    },
    
    canProceedToStep3: (state) => {
      return true // Clinical notes are optional
    },
    
    canSave: (state) => {
      return state.demographics.name && state.demographics.name.length >= 2
    },
    
    wizardPayload: (state) => {
      return {
        // Demographics
        file_number: state.demographics.file_number || null,
        name: state.demographics.name,
        gender: state.demographics.gender,
        age: state.demographics.age,
        phone: state.demographics.phone,
        billing: state.demographics.billing,
        currency_id: state.currency_id,
        
        // Clinical
        complaints: state.clinical.complaints,
        diagnosis: state.clinical.diagnosis,
        treatment_plan_notes: state.clinical.treatment_plan_notes,
        review_date: state.clinical.review_date,
        
        // Pending procedures - JSON array stored in customers table
        pending_procedures: state.clinical.treatments.map(t => ({
          item_id: t.item_id,
          name: t.name,
          description: t.description,
          quantity: t.quantity || 1,
          price: t.price,
        })),
        
        // Finances (for reference, actual invoice created on redirect)
        initial_payment_method: state.finances.payment_method_id,
      }
    },
  },
  
  actions: {
    nextStep() {
      if (this.currentStep < this.totalSteps) {
        this.saveDraftToLocal()
        this.currentStep++
      }
    },
    
    prevStep() {
      if (this.currentStep > 1) {
        this.currentStep--
      }
    },
    
    goToStep(step) {
      if (step >= 1 && step <= this.totalSteps) {
        this.currentStep = step
      }
    },
    
    // Treatment management
    addTreatment(item) {
      // Check if already added
      const existing = this.clinical.treatments.find(t => t.item_id === item.id)
      if (existing) {
        existing.quantity = (existing.quantity || 1) + 1
        return
      }
      
      this.clinical.treatments.push({
        item_id: item.id,
        name: item.name,
        description: item.description,
        price: item.price,
        quantity: 1,
      })
    },
    
    removeTreatment(index) {
      this.clinical.treatments.splice(index, 1)
    },
    
    updateTreatmentQuantity(index, quantity) {
      if (this.clinical.treatments[index]) {
        this.clinical.treatments[index].quantity = Math.max(1, quantity)
      }
    },
    
    // Draft management
    saveDraftToLocal() {
      const draft = {
        demographics: this.demographics,
        clinical: this.clinical,
        finances: this.finances,
        currency_id: this.currency_id,
        currentStep: this.currentStep,
        savedAt: new Date().toISOString(),
      }
      localStorage.setItem('patientWizardDraft', JSON.stringify(draft))
      this.isDraft = true
    },
    
    loadDraftFromLocal() {
      const draft = localStorage.getItem('patientWizardDraft')
      if (draft) {
        try {
          const data = JSON.parse(draft)
          this.demographics = data.demographics
          this.clinical = data.clinical
          this.finances = data.finances
          this.currency_id = data.currency_id
          this.currentStep = data.currentStep || 1
          this.isDraft = true
          return true
        } catch (e) {
          console.error('Failed to parse draft:', e)
          return false
        }
      }
      return false
    },
    
    clearDraft() {
      localStorage.removeItem('patientWizardDraft')
      this.isDraft = false
    },
    
    // Reset to initial state
    resetWizard() {
      this.currentStep = 1
      this.demographics = {
        file_number: '',
        name: '',
        gender: null,
        age: null,
        phone: '',
        billing: {
          address_street_1: '',
          city: '',
          state: '',
          country_id: null,
          zip: '',
        },
      }
      this.clinical = {
        complaints: '',
        diagnosis: '',
        treatment_plan_notes: '',
        treatments: [],
        review_date: null,
      }
      this.finances = {
        amount_paid: 0,
        payment_method_id: null,
        create_invoice_now: true,
      }
      this.currency_id = null
      this.isDraft = false
      this.clearDraft()
    },
    
    // API calls
    async savePatient(action = 'save_only') {
      this.isSaving = true
      this.saveAction = action
      const notificationStore = useNotificationStore()
      
      try {
        const response = await axios.post('/api/v1/patients/wizard', this.wizardPayload)
        const newPatientId = response.data?.data?.id
        
        notificationStore.showNotification({
          type: 'success',
          message: 'Patient saved successfully',
        })
        
        this.clearDraft()
        this.resetWizard()
        
        // Return response with save action for parent to handle redirect
        return {
          ...response,
          saveAction: action,
          patientId: newPatientId,
        }
      } catch (err) {
        handleError(err)
        throw err
      } finally {
        this.isSaving = false
      }
    },
    
    // Called after "Save & Bill" to redirect to invoice creation
    // IMPORTANT: Use `customer` param (not `customer_id`) - this is what the existing stores expect
    getInvoiceCreateUrl(patientId) {
      return `/admin/invoices/create?customer=${patientId}`
    },
    
    getQuotationCreateUrl(patientId) {
      return `/admin/estimates/create?customer=${patientId}`
    },
  },
})
```

### Component Structure

```
resources/scripts/admin/components/
├── modal-components/
│   ├── CustomerModal.vue (keep existing for backward compatibility)
│   └── PatientWizardModal.vue (NEW - main wizard modal)
│
└── patient-wizard/
    ├── StepIndicator.vue (progress indicator)
    ├── StepDemographics.vue (Step 1)
    ├── StepClinicalNotes.vue (Step 2)
    ├── StepFinances.vue (Step 3)
    ├── TreatmentSelector.vue (procedure selection table)
    └── WizardNavigation.vue (Back/Next/Save buttons)
```

---

## Language/Terminology Changes

### Update lang/en.json

```json
{
  "navigation": {
    "customers": "Patients",
    "items": "Procedures",
    "estimates": "Quotations"
  },
  
  "customers": {
    "title": "Patients",
    "add_customer": "Add Patient",
    "new_customer": "New Patient",
    "save_customer": "Save Patient",
    "update_customer": "Update Patient",
    "edit_customer": "Edit Patient",
    "customer": "Patient | Patients",
    "no_customers": "No patients yet!",
    "no_customers_found": "No patients found!",
    "select_a_customer": "Select a patient",
    "created_message": "Patient created successfully",
    "updated_message": "Patient updated successfully",
    "deleted_message": "Patient deleted successfully | Patients deleted successfully",
    "confirm_delete": "You will not be able to recover this patient and all related records."
  },
  
  "items": {
    "title": "Procedures",
    "item": "Procedure | Procedures",
    "add_item": "Add Procedure",
    "new_item": "New Procedure",
    "save_item": "Save Procedure",
    "update_item": "Update Procedure",
    "edit_item": "Edit Procedure",
    "no_items": "No procedures yet!",
    "created_message": "Procedure created successfully",
    "updated_message": "Procedure updated successfully",
    "deleted_message": "Procedure deleted successfully | Procedures deleted successfully"
  },
  
  "estimates": {
    "title": "Quotations",
    "estimate": "Quotation | Quotations",
    "new_estimate": "New Quotation",
    "add_estimate": "Add Quotation",
    "save_estimate": "Save Quotation",
    "edit_estimate": "Edit Quotation",
    "no_estimates": "No quotations yet!",
    "created_message": "Quotation created successfully",
    "updated_message": "Quotation updated successfully",
    "deleted_message": "Quotation deleted successfully | Quotations deleted successfully"
  },
  
  "patient_wizard": {
    "title": "New Patient",
    "edit_title": "Edit Patient",
    "step_demographics": "Demographics",
    "step_clinical": "Clinical Notes",
    "step_finances": "Finances",
    
    "file_number": "File No.",
    "file_number_placeholder": "e.g., ADS-001",
    "patient_name": "Patient's Name",
    "sex": "Sex",
    "male": "Male",
    "female": "Female",
    "age": "Age",
    "address": "Address",
    "contact": "Contact",
    
    "complaints": "Complaints",
    "complaints_placeholder": "Chief complaint...",
    "diagnosis": "Diagnosis",
    "diagnosis_placeholder": "Diagnosis details...",
    "plan": "Plan",
    "plan_placeholder": "Treatment plan (e.g., stabilize jaw first, then braces)...",
    "procedures": "Procedures",
    "procedures_placeholder": "Search and add procedures...",
    "review_date": "Review Date",
    
    "billable_amount": "Billable Amount",
    "amount_paid": "Amount Paid",
    "balance": "Balance",
    "payment_method": "Payment Method",
    "create_invoice_now": "Create invoice and record payment now",
    
    "back": "Back",
    "next": "Next",
    "save_patient": "Save Patient",
    "save_and_bill": "Save & Bill",
    "file_number_taken": "This file number is already in use",
    
    "draft_found": "Draft Found",
    "draft_found_message": "You have an unsaved patient draft. Would you like to continue?",
    "continue_draft": "Continue Draft",
    "start_fresh": "Start Fresh",
    
    "no_procedures_selected": "No procedures selected",
    "add_procedure_hint": "Search above to add procedures"
  },
  
  "payment_methods": {
    "cash": "Cash",
    "mtn_momo": "MTN MoMo 0769969282",
    "bank": "Bank Transfer"
  }
}
```

---

## Patient Wizard Modal

### Main Modal Component

```vue
<!-- resources/scripts/admin/components/modal-components/PatientWizardModal.vue -->
<template>
  <BaseModal
    :show="modalActive"
    size="lg"
    @close="handleClose"
    @open="handleOpen"
  >
    <template #header>
      <div class="flex items-center justify-between w-full">
        <h3 class="text-lg font-medium">
          {{ isEdit ? $t('patient_wizard.edit_title') : $t('patient_wizard.title') }}
        </h3>
        <BaseIcon
          name="XMarkIcon"
          class="w-5 h-5 text-gray-500 cursor-pointer hover:text-gray-700"
          @click="handleClose"
        />
      </div>
    </template>

    <!-- Step Indicator -->
    <StepIndicator
      :current-step="wizardStore.currentStep"
      :total-steps="wizardStore.totalSteps"
      :step-titles="wizardStore.stepTitles"
      class="px-6 pt-4"
    />

    <!-- Step Content -->
    <div class="px-6 py-4 min-h-[400px]">
      <StepDemographics
        v-show="wizardStore.currentStep === 1"
        :v="v$"
      />
      
      <StepClinicalNotes
        v-show="wizardStore.currentStep === 2"
      />
      
      <StepFinances
        v-show="wizardStore.currentStep === 3"
      />
    </div>

    <!-- Navigation -->
    <WizardNavigation
      :is-saving="wizardStore.isSaving"
      :has-procedures="wizardStore.clinical.treatments.length > 0"
      @back="wizardStore.prevStep"
      @next="handleNext"
      @save-only="handleSaveOnly"
      @save-and-bill="handleSaveAndBill"
    />
  </BaseModal>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import useVuelidate from '@vuelidate/core'
import { required, minLength, helpers } from '@vuelidate/validators'

import { useModalStore } from '@/scripts/stores/modal'
import { usePatientWizardStore } from '@/scripts/admin/stores/patient-wizard'
import { useCompanyStore } from '@/scripts/admin/stores/company'

import StepIndicator from '@/scripts/admin/components/patient-wizard/StepIndicator.vue'
import StepDemographics from '@/scripts/admin/components/patient-wizard/StepDemographics.vue'
import StepClinicalNotes from '@/scripts/admin/components/patient-wizard/StepClinicalNotes.vue'
import StepFinances from '@/scripts/admin/components/patient-wizard/StepFinances.vue'
import WizardNavigation from '@/scripts/admin/components/patient-wizard/WizardNavigation.vue'

const { t } = useI18n()
const modalStore = useModalStore()
const wizardStore = usePatientWizardStore()
const companyStore = useCompanyStore()

const isEdit = computed(() => modalStore.data?.isEdit || false)

const modalActive = computed(
  () => modalStore.active && modalStore.componentName === 'PatientWizardModal'
)

// Validation rules
const rules = computed(() => ({
  demographics: {
    name: {
      required: helpers.withMessage(t('validation.required'), required),
      minLength: helpers.withMessage(
        t('validation.name_min_length', { count: 2 }),
        minLength(2)
      ),
    },
  },
}))

const v$ = useVuelidate(rules, wizardStore)

function handleOpen() {
  // Check for draft
  const hasDraft = wizardStore.loadDraftFromLocal()
  
  if (!hasDraft && !isEdit.value) {
    // Set default currency
    wizardStore.currency_id = companyStore.selectedCompanyCurrency?.id
  }
}

function handleClose() {
  // Save draft before closing
  if (wizardStore.demographics.name) {
    wizardStore.saveDraftToLocal()
  }
  
  modalStore.closeModal()
}

async function handleNext() {
  // Validate current step
  if (wizardStore.currentStep === 1) {
    v$.value.demographics.$touch()
    if (v$.value.demographics.$invalid) {
      return
    }
  }
  
  wizardStore.nextStep()
}

// Handle "Save Patient Only" button
async function handleSaveOnly() {
  await handleSave('save_only')
}

// Handle "Save & Bill" button - saves and redirects to invoice creation
async function handleSaveAndBill() {
  await handleSave('save_and_bill')
}

async function handleSave(action) {
  v$.value.$touch()
  
  if (!wizardStore.canSave) {
    return
  }
  
  try {
    const result = await wizardStore.savePatient(action)
    
    modalStore.closeModal()
    
    // If "Save & Bill" was clicked, redirect to invoice creation with patient pre-selected
    // IMPORTANT: Use `customer` param - this matches existing store behavior in invoice.js/estimate.js
    if (action === 'save_and_bill' && result.patientId) {
      // Use Vue Router to navigate
      router.push({
        path: '/admin/invoices/create',
        query: { customer: result.patientId }  // NOT customer_id!
      })
    }
  } catch (error) {
    // Error handled in store
  }
}
</script>
```

### Treatment Selector Component

```vue
<!-- resources/scripts/admin/components/patient-wizard/TreatmentSelector.vue -->
<template>
  <div class="treatment-selector">
    <!-- Search and Add -->
    <div class="mb-4">
      <BaseMultiselect
        v-model="selectedItem"
        :options="itemStore.items"
        label="name"
        value-prop="id"
        searchable
        :placeholder="$t('patient_wizard.procedures_placeholder')"
        @select="handleSelect"
      >
        <template #option="{ option }">
          <div class="flex justify-between items-center w-full">
            <span>{{ option.name }}</span>
            <span class="text-sm text-gray-500">
              {{ formatMoney(option.price, currency) }}
            </span>
          </div>
        </template>
      </BaseMultiselect>
    </div>

    <!-- Selected Treatments Table -->
    <div v-if="treatments.length" class="border rounded-lg overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr class="text-left text-xs font-medium text-gray-500 uppercase">
            <th class="px-4 py-3">Procedure</th>
            <th class="px-4 py-3 w-20 text-center">Qty</th>
            <th class="px-4 py-3 w-28 text-right">Price</th>
            <th class="px-4 py-3 w-28 text-right">Total</th>
            <th class="px-4 py-3 w-12"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="(treatment, index) in treatments" :key="treatment.item_id">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900">{{ treatment.name }}</div>
              <div v-if="treatment.description" class="text-sm text-gray-500 truncate max-w-xs">
                {{ treatment.description }}
              </div>
            </td>
            <td class="px-4 py-3">
              <BaseInput
                v-model.number="treatment.quantity"
                type="number"
                min="1"
                class="w-16 text-center"
                @change="updateQuantity(index, treatment.quantity)"
              />
            </td>
            <td class="px-4 py-3 text-right text-gray-600">
              {{ formatMoney(treatment.price, currency) }}
            </td>
            <td class="px-4 py-3 text-right font-medium text-gray-900">
              {{ formatMoney(treatment.price * treatment.quantity, currency) }}
            </td>
            <td class="px-4 py-3 text-center">
              <BaseIcon
                name="TrashIcon"
                class="w-4 h-4 text-red-500 cursor-pointer hover:text-red-700"
                @click="removeTreatment(index)"
              />
            </td>
          </tr>
        </tbody>
        <tfoot class="bg-gray-50">
          <tr>
            <td colspan="3" class="px-4 py-3 text-right font-medium text-gray-700">
              Total:
            </td>
            <td class="px-4 py-3 text-right font-bold text-primary-600 text-lg">
              {{ formatMoney(totalAmount, currency) }}
            </td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12 border-2 border-dashed border-gray-300 rounded-lg">
      <BaseIcon name="ClipboardDocumentListIcon" class="w-12 h-12 mx-auto text-gray-400" />
      <p class="mt-2 text-gray-500">{{ $t('patient_wizard.no_procedures_selected') }}</p>
      <p class="text-sm text-gray-400">{{ $t('patient_wizard.add_procedure_hint') }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, inject, onMounted } from 'vue'
import { useItemStore } from '@/scripts/admin/stores/item'
import { usePatientWizardStore } from '@/scripts/admin/stores/patient-wizard'
import { useCompanyStore } from '@/scripts/admin/stores/company'

const itemStore = useItemStore()
const wizardStore = usePatientWizardStore()
const companyStore = useCompanyStore()
const utils = inject('utils')

const selectedItem = ref(null)

const treatments = computed(() => wizardStore.clinical.treatments)
const currency = computed(() => companyStore.selectedCompanyCurrency)

const totalAmount = computed(() => {
  return treatments.value.reduce(
    (sum, t) => sum + (t.price * (t.quantity || 1)),
    0
  )
})

function formatMoney(amount, currency) {
  return utils.formatMoney(amount, currency)
}

function handleSelect(itemId) {
  const item = itemStore.items.find(i => i.id === itemId)
  if (item) {
    wizardStore.addTreatment(item)
  }
  selectedItem.value = null
}

function updateQuantity(index, quantity) {
  wizardStore.updateTreatmentQuantity(index, quantity)
}

function removeTreatment(index) {
  wizardStore.removeTreatment(index)
}

onMounted(() => {
  // Ensure items are loaded
  if (!itemStore.items.length) {
    itemStore.fetchItems({ limit: 'all' })
  }
})
</script>
```

---

## Auto-Population Logic

### "Save & Bill" Workflow (Recommended UX)

> **Architecture Note**: The "Save & Bill" button redirects to `/invoices/create?customer=X` 
> (note: `customer` not `customer_id` - this matches existing store behavior).
> The store's `fetchInvoiceInitialSettings()` already handles `route.query.customer` to preselect.

### How It Already Works (Existing Code)

The invoice/estimate stores **already support** customer preselection via URL:

```javascript
// Already in resources/scripts/admin/stores/invoice.js (line ~499)
if (route.query.customer) {
  let response = await customerStore.fetchCustomer(route.query.customer)
  this.newInvoice.customer = response.data.data
  this.newInvoice.customer_id = response.data.data.id
}
```

So we only need to:
1. Add `pending_procedures` to `CustomerResource.php` (so it's returned by API)
2. Add a watcher to populate items from `pending_procedures`
3. Clear `pending_procedures` **server-side** when invoice is created

### Enhanced Invoice Create (Minimal Changes)

```javascript
// Add to resources/scripts/admin/views/invoices/create/InvoiceCreate.vue

import { useNotificationStore } from '@/scripts/stores/notification'
const notificationStore = useNotificationStore()

// Watch for customer selection (works for both URL param AND manual selection)
watch(
  () => invoiceStore.newInvoice.customer,
  (newCustomer, oldCustomer) => {
    if (!newCustomer?.id || newCustomer.id === oldCustomer?.id) return
    
    // Auto-populate from pending_procedures JSON if present
    if (newCustomer.pending_procedures?.length > 0) {
      populatePendingProcedures(newCustomer.pending_procedures)
    }
    
    // Auto-populate notes with treatment plan
    if (newCustomer.treatment_plan_notes && !invoiceStore.newInvoice.notes) {
      invoiceStore.newInvoice.notes = newCustomer.treatment_plan_notes
    }
  },
  { immediate: false }
)

// Helper function to populate invoice items from pending procedures
function populatePendingProcedures(procedures) {
  // Clear existing items
  invoiceStore.newInvoice.items = []
  
  // Add each procedure as an invoice item
  procedures.forEach((proc) => {
    invoiceStore.addItem()
    
    invoiceStore.$patch((state) => {
      const itemIndex = state.newInvoice.items.length - 1
      state.newInvoice.items[itemIndex] = {
        ...state.newInvoice.items[itemIndex],
        item_id: proc.item_id,
        name: proc.name || '',
        description: proc.description || '',
        price: proc.price,
        quantity: proc.quantity || 1,
      }
    })
  })
  
  notificationStore.showNotification({
    type: 'success',
    message: `Added ${procedures.length} procedure(s) from patient record`,
  })
}
```

### Server-Side Pending Procedures Clearing (Critical!)

**Don't clear client-side** - if the browser crashes mid-flow, pending procedures remain orphaned.
Instead, clear in the backend when invoice is successfully created:

```php
// Modify app/Http/Controllers/V1/Admin/Invoice/InvoicesController.php - store method

public function store(InvoicesRequest $request)
{
    $this->authorize('create', Invoice::class);

    $invoice = Invoice::createInvoice($request);

    // CLEAR PENDING PROCEDURES after successful invoice creation
    if ($request->customer_id) {
        $customer = Customer::find($request->customer_id);
        if ($customer && $customer->pending_procedures) {
            $customer->update(['pending_procedures' => null]);
        }
    }

    return new InvoiceResource($invoice);
}
```

Same for estimates (quotations):
```php
// Modify app/Http/Controllers/V1/Admin/Estimate/EstimatesController.php - store method

public function store(EstimatesRequest $request)
{
    $this->authorize('create', Estimate::class);

    $estimate = Estimate::createEstimate($request);

    // CLEAR PENDING PROCEDURES after successful estimate creation
    if ($request->customer_id) {
        $customer = Customer::find($request->customer_id);
        if ($customer && $customer->pending_procedures) {
            $customer->update(['pending_procedures' => null]);
        }
    }

    return new EstimateResource($estimate);
}
```

### Why Server-Side Clearing is Better

| Client-Side Clearing | Server-Side Clearing |
|---------------------|---------------------|
| Separate API call after save | Same transaction as invoice creation |
| If browser crashes → stale data | Always clears on success |
| Race conditions possible | Atomic operation |
| Extra network request | No extra request |

---

## Invoice Payment Method Display

### The Problem

Invoices can have **multiple payments** with **different payment methods**. The plan needs a clear rule 
for what to display on the invoice PDF.

### Recommended Approach

**For UNPAID invoices:** Show patient's preferred/initial payment method (from `initial_payment_method`)

**For PAID/PARTIAL invoices:** Show list of actual payments with their methods

```blade
{{-- Add to invoice PDF templates --}}

@if($invoice->payments->count() > 0)
    {{-- Show actual payments received --}}
    <div class="payment-info">
        <h4>Payments Received</h4>
        <table>
            @foreach($invoice->payments as $payment)
            <tr>
                <td>{{ $payment->formattedPaymentDate }}</td>
                <td>{{ $payment->paymentMethod->name ?? 'N/A' }}</td>
                <td>{{ $payment->formatted_amount }}</td>
            </tr>
            @endforeach
        </table>
    </div>
@elseif($invoice->customer && $invoice->customer->initial_payment_method)
    {{-- Show preferred payment method for unpaid invoices --}}
    <div class="payment-method-hint">
        <strong>Preferred Payment:</strong> {{ $invoice->customer->initial_payment_method }}
    </div>
@endif
```

### Alternative: Simple "Last Payment Method"

If you just want to show the most recent payment method:

```blade
@if($invoice->payments->count() > 0)
    <div class="payment-method">
        <strong>Paid via:</strong> {{ $invoice->payments->last()->paymentMethod->name ?? 'Cash' }}
    </div>
@endif
```

---

## Receipt Template

### Dental Receipt PDF Template

```blade
{{-- resources/views/app/pdf/payment/dental-receipt.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $payment->payment_number }}</title>
    <style>
        @page {
            size: A5 landscape;
            margin: 8mm;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .receipt-container {
            max-width: 100%;
            padding: 5mm;
        }
        
        /* Header */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .header-left {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        
        .header-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
            text-align: right;
            font-size: 9pt;
            color: #555;
        }
        
        .logo {
            max-height: 45px;
            max-width: 150px;
        }
        
        .company-name {
            font-size: 16pt;
            font-weight: bold;
            color: #1a56db;
            margin: 0;
        }
        
        .company-tagline {
            font-size: 9pt;
            color: #666;
            margin: 0;
        }
        
        /* Title Row */
        .title-row {
            display: table;
            width: 100%;
            margin: 15px 0;
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
            padding: 8px 0;
        }
        
        .receipt-no {
            display: table-cell;
            width: 25%;
            vertical-align: middle;
        }
        
        .receipt-title {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: middle;
        }
        
        .receipt-title span {
            border: 2px solid #333;
            padding: 4px 20px;
            font-weight: bold;
            font-size: 12pt;
            letter-spacing: 2px;
        }
        
        .receipt-date {
            display: table-cell;
            width: 25%;
            text-align: right;
            vertical-align: middle;
        }
        
        /* Receipt Body */
        .receipt-row {
            margin: 12px 0;
            display: table;
            width: 100%;
        }
        
        .label {
            font-weight: bold;
            display: inline;
        }
        
        .value {
            display: inline;
            margin-left: 5px;
        }
        
        .dotted-underline {
            border-bottom: 1px dashed #666;
            display: inline-block;
            min-width: 250px;
            padding-bottom: 2px;
        }
        
        .double-row {
            display: table;
            width: 100%;
            margin: 12px 0;
        }
        
        .double-row-left,
        .double-row-right {
            display: table-cell;
            width: 50%;
        }
        
        /* Footer */
        .footer {
            display: table;
            width: 100%;
            margin-top: 25px;
        }
        
        .amount-box-container {
            display: table-cell;
            width: 40%;
            vertical-align: bottom;
        }
        
        .amount-box {
            border: 2px solid #333;
            padding: 5px 15px;
            display: inline-block;
            font-weight: bold;
            font-size: 11pt;
        }
        
        .signature-container {
            display: table-cell;
            width: 60%;
            text-align: right;
            vertical-align: bottom;
        }
        
        .signature-line {
            border-bottom: 1px solid #333;
            display: inline-block;
            width: 180px;
            margin-bottom: 3px;
        }
        
        .signature-label {
            font-size: 9pt;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        {{-- Header --}}
        <div class="header">
            <div class="header-left">
                @if($logo)
                    <img src="{{ \App\Space\ImageUtils::toBase64Src($logo) }}" class="logo" alt="Logo">
                @else
                    <p class="company-name">ALBERTINE</p>
                    <p class="company-tagline">DENTAL SURGERY</p>
                    <p class="company-tagline" style="font-style: italic;">Smile Better</p>
                @endif
            </div>
            <div class="header-right">
                {!! $company_address !!}
            </div>
        </div>

        {{-- Title Row --}}
        <div class="title-row">
            <div class="receipt-no">
                <span class="label">No.</span>
                <span style="font-size: 14pt; font-weight: bold; color: #1a56db;">
                    {{ $payment->sequence_number ?? $payment->payment_number }}
                </span>
            </div>
            <div class="receipt-title">
                <span>RECEIPT</span>
            </div>
            <div class="receipt-date">
                <span class="label">Date:</span>
                <span>{{ $payment->formattedPaymentDate }}</span>
            </div>
        </div>

        {{-- Receipt Body --}}
        <div class="receipt-row">
            <span class="label">Received with thanks from</span>
            <span class="dotted-underline">{{ $payment->customer->name }}</span>
        </div>

        <div class="receipt-row">
            <span class="label">The sum of shillings</span>
            <span class="dotted-underline">
                {{ number_format($payment->amount / 100) }}/=
                ({{ ucfirst((new \NumberFormatter('en', \NumberFormatter::SPELLOUT))->format($payment->amount / 100)) }} shillings only)
            </span>
        </div>

        <div class="receipt-row">
            <span class="label">Being payment of</span>
            <span class="dotted-underline">
                @if($payment->invoice)
                    @if($payment->customer->treatment)
                        {{ $payment->customer->treatment }}
                    @else
                        Invoice #{{ $payment->invoice->invoice_number }}
                    @endif
                @else
                    {{ $payment->notes ?: 'Dental Services' }}
                @endif
            </span>
        </div>

        <div class="double-row">
            <div class="double-row-left">
                <span class="label">Cash/cheque No.</span>
                <span class="dotted-underline" style="min-width: 120px;">
                    {{ $payment->paymentMethod->name ?? 'Cash' }}
                </span>
            </div>
            <div class="double-row-right" style="text-align: right;">
                <span class="label">Balance</span>
                <span class="dotted-underline" style="min-width: 120px;">
                    @if($payment->invoice && $payment->invoice->due_amount > 0)
                        {{ number_format($payment->invoice->due_amount / 100) }}/=
                    @else
                        NIL
                    @endif
                </span>
            </div>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <div class="amount-box-container">
                <span class="label">Shs</span>
                <span class="amount-box">{{ number_format($payment->amount / 100) }}</span>
            </div>
            <div class="signature-container">
                <span class="label">Signature</span>
                <div class="signature-line"></div>
                <br>
                <span class="signature-label">
                    For {{ $payment->company->name ?? 'ALBERTINE DENTAL SURGERY' }}
                </span>
            </div>
        </div>
    </div>
</body>
</html>
```

---

## Implementation Checklist

### Phase 1: Database & Backend Foundation (Day 1-2)

> **Simplified**: No separate `patient_treatments` table. Using JSON column instead.

- [ ] Create migration `add_dental_patient_fields_to_customers_table`
  - Includes `pending_procedures` JSON column
- [ ] Run migrations: `php artisan migrate`
- [ ] Update `app/Models/Customer.php`:
  - Add fillable, casts, helper methods
  - **[Refinement 1]** Update BOTH `scopeWhereDisplayName()` AND `scopeWhereSearch()` to include `file_number`
- [ ] Update `app/Http/Requests/CustomerRequest.php` (validation, payload)
- [ ] **[CRITICAL]** Update `app/Http/Resources/CustomerResource.php` - add ALL patient fields including `pending_procedures`
- [ ] Add `numberToWords()` helper function (uses native `NumberFormatter`)
- [ ] **[Refinement 6]** Create `DentalPaymentMethodSeeder.php` (with `company_id`, `type=GENERAL`, `active=true`)
- [ ] Test in Tinker: `Customer::first()->pending_procedures`

### Phase 2: API Endpoints (Day 2-3)

> **Simplified**: Only one controller needed (no separate treatments controller)

- [ ] Create `app/Http/Controllers/V1/Admin/Patient/PatientWizardController.php`
  - Include `store()`, `saveDraft()`, `getDraft()`, `clearDraft()`, `clearPending()`
  - **[Refinement 5]** Add `checkFileNumber()` endpoint for async validation
- [ ] Create `app/Http/Requests/PatientWizardRequest.php`
- [ ] Add routes to `routes/api.php`
- [ ] Test endpoints with curl/Postman
- [ ] Add proper authorization policies
- [ ] Run seeder: `php artisan db:seed --class=DentalPaymentMethodSeeder`

### Phase 3: Language Files (Day 3)

- [ ] Update `lang/en.json` - Customers → Patients
- [ ] Update `lang/en.json` - Items → Procedures
- [ ] Update `lang/en.json` - Estimates → Quotations
- [ ] Add `patient_wizard` section to `lang/en.json`
- [ ] Test UI labels in browser

### Phase 4: Patient Wizard Frontend (Day 4-6)

- [ ] Create `resources/scripts/admin/stores/patient-wizard.js`
- [ ] Create `resources/scripts/admin/components/modal-components/PatientWizardModal.vue`
  - **[Refinement 4]** Implement `isEdit` mode with only 2 steps (Demographics + Clinical)
- [ ] Create `resources/scripts/admin/components/patient-wizard/StepIndicator.vue`
- [ ] Create `resources/scripts/admin/components/patient-wizard/StepDemographics.vue`
  - **[Refinement 5]** Add async file number validation on blur
- [ ] Create `resources/scripts/admin/components/patient-wizard/StepClinicalNotes.vue`
- [ ] Create `resources/scripts/admin/components/patient-wizard/TreatmentSelector.vue`
  - **[Refinement 2]** Make procedure name/description fields editable
- [ ] Create `resources/scripts/admin/components/patient-wizard/StepFinances.vue`
- [ ] Create `resources/scripts/admin/components/patient-wizard/WizardNavigation.vue`
  - Handle edit mode (hide "Save & Bill" button)
- [ ] Register modal in modal store
- [ ] Update "Add Customer" buttons to open wizard
- [ ] Implement draft save/restore
- [ ] Test complete wizard flow (create + edit modes)

### Phase 5: Auto-Population (Day 6-7)

> **Note**: Existing stores already handle `?customer=X` param. We just add the watcher.

- [ ] Add watcher in `InvoiceCreate.vue` for `pending_procedures` auto-population
- [ ] Add `populatePendingProcedures()` helper function
- [ ] Add same logic to `EstimateCreate.vue` (Quotations)
- [ ] **[SERVER-SIDE]** Modify `InvoicesController::store()` to clear `pending_procedures` after creation
- [ ] **[SERVER-SIDE]** Modify `EstimatesController::store()` to clear `pending_procedures` after creation
- [ ] Test "Save & Bill" → Invoice flow (uses `?customer=X`, NOT `?customer_id=X`)

### Phase 6: Receipt Template (Day 7-8)

- [ ] Create `resources/views/app/pdf/payment/dental-receipt.blade.php`
- [ ] Update `app/Models/Payment.php` to use dental template
- [ ] Test PDF generation
- [ ] Fine-tune styling to match physical receipt
- [ ] Test with various amounts (number to words via `NumberFormatter`)

### Phase 7: Invoice PDF Updates (Day 8)

- [ ] Update `resources/views/app/pdf/invoice/*.blade.php` templates:
  - Add payment method section (see "Invoice Payment Method Display" section)
  - ~~**[Refinement 3]** Add Review Date display~~ **ALREADY DONE** - templates have `customer_review_date`
- [ ] Test invoice PDFs with payment methods

### Phase 8: Testing & Polish (Day 9-10)

- [ ] End-to-end test: Create patient → Create invoice → Record payment → View receipt
- [ ] Test draft system (close modal unexpectedly, reopen)
- [ ] Test auto-population edge cases
- [ ] Test with existing patients (backward compatibility)
- [ ] UI polish and responsive design
- [ ] Fix any bugs found

---

## Files to Modify

### Backend Files

> **Simplified**: Removed `PatientTreatment` model, resource, and controller. Using JSON column instead.

| File | Action | Changes |
|------|--------|---------|
| `database/migrations/*` | CREATE | 1 migration (patient fields + JSON column) |
| `database/seeders/DentalPaymentMethodSeeder.php` | CREATE | Seed per company with `company_id`, `type=GENERAL`, `active=true` |
| `app/Models/Customer.php` | MODIFY | Add fillable, casts, helpers, update `scopeWhereDisplayName()` AND `scopeWhereSearch()` |
| `app/Http/Resources/CustomerResource.php` | MODIFY | **CRITICAL**: Add ALL patient fields including `pending_procedures` |
| `app/Http/Requests/CustomerRequest.php` | MODIFY | Add validation rules |
| `app/Http/Requests/PatientWizardRequest.php` | CREATE | New request |
| `app/Http/Controllers/V1/Admin/Patient/PatientWizardController.php` | CREATE | Wizard + `checkFileNumber()` endpoint |
| `app/Http/Controllers/V1/Admin/Invoice/InvoicesController.php` | MODIFY | Clear `pending_procedures` on invoice creation |
| `app/Http/Controllers/V1/Admin/Estimate/EstimatesController.php` | MODIFY | Clear `pending_procedures` on estimate creation |
| `app/helpers.php` | MODIFY | Add `numberToWords()` using native `NumberFormatter` |
| `routes/api.php` | MODIFY | Add new routes including file number check |

### Frontend Files

| File | Action | Changes |
|------|--------|---------|
| `lang/en.json` | MODIFY | Rename terms, add wizard translations |
| `resources/scripts/admin/stores/patient-wizard.js` | CREATE | New Pinia store |
| `resources/scripts/admin/components/modal-components/PatientWizardModal.vue` | CREATE | Main wizard modal |
| `resources/scripts/admin/components/patient-wizard/*.vue` | CREATE | 6 new components |
| `resources/scripts/admin/views/invoices/create/InvoiceCreate.vue` | MODIFY | Add auto-population |
| `resources/scripts/admin/views/estimates/create/EstimateCreate.vue` | MODIFY | Add auto-population |

### PDF Templates

| File | Action | Changes |
|------|--------|---------|
| `resources/views/app/pdf/payment/dental-receipt.blade.php` | CREATE | New receipt template |
| `resources/views/app/pdf/invoice/*.blade.php` | MODIFY | Add payment method |

---

## Notes

### Backward Compatibility

- Existing customers will continue to work
- New fields are nullable
- `pending_procedures` JSON is nullable (null = no pending procedures)
- Original `CustomerModal.vue` kept for fallback
- API endpoints are additive (no breaking changes)

### Architecture Decisions Explained

1. **JSON vs Separate Table for Pending Procedures**
   - Separate table creates "split source of truth"
   - Need complex sync logic (billed/unbilled, delete cascades)
   - JSON is simpler: data is in ONE place at a time
   - When invoice is created → read JSON → populate items → clear JSON

2. **Native NumberFormatter vs Custom Class**
   - PHP's `NumberFormatter` with `SPELLOUT` is built-in
   - Handles edge cases, large numbers, negatives
   - Supports 100+ languages if needed later
   - Zero maintenance code

3. **"Save & Bill" Redirect vs Watcher**
   - URL param `?customer_id=X` is explicit and debuggable
   - No "magic" watcher behavior to understand
   - User sees seamless flow: Save → Invoice page opens

### Future Enhancements

1. **File Number Auto-Generation**: Add configurable prefix (e.g., "ADS-") with auto-incrementing number
2. **Visit History**: Track multiple visits per patient (could use JSON array of visit objects)
3. **Appointment Integration**: Link appointments to patient treatments
4. **Treatment Templates**: Pre-defined treatment packages (JSON presets)
5. **Patient Portal**: Allow patients to view their treatment history

### Testing Notes

- Test with both fresh database and existing data
- Test currency formatting (UGX shillings)
- Test number-to-words for large amounts (NumberFormatter handles billions)
- Test PDF generation on different paper sizes
- Test mobile responsiveness of wizard
- Test "Save & Bill" flow: Patient wizard → Invoice create → Save → Check JSON cleared

---

*Document Version: 1.3*
*Created: December 22, 2025*
*Updated: December 22, 2025*
- *v1.1: Architecture improvements (JSON handoff, native NumberFormatter, Save & Bill UX)*
- *v1.2: Technical refinements (file number search, editable procedures, review date on invoice, edit mode logic, async validation, payment seeder)*
- *v1.3: Codebase alignment fixes (URL param `?customer=`, scopeWhereDisplayName, company-scoped seeder, server-side pending clear, CustomerResource fields)*
*For: InvoiceShelf Custom - Albertine Dental Surgery*

