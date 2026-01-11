<template>
  <BaseModal
    :show="modalActive"
    @close="closeModal"
    @open="initializeWizard"
  >
    <template #header>
      <div class="flex justify-between w-full">
        <span>{{ wizardStore.isEditMode ? $t('patient_wizard.edit_title') : $t('patient_wizard.title') }}</span>
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-gray-500 cursor-pointer"
          @click="closeModal"
        />
      </div>
    </template>

    <div class="px-6 pb-3">
      <!-- Step Indicator -->
      <div class="flex items-center justify-center mb-6">
        <div
          v-for="step in displaySteps"
          :key="step.number"
          class="flex items-center"
        >
          <div
            :class="[
              'flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium',
              wizardStore.currentStep >= step.number
                ? 'bg-primary-500 text-white'
                : 'bg-gray-200 text-gray-500'
            ]"
          >
            {{ step.number }}
          </div>
          <span 
            :class="[
              'ml-2 text-sm',
              wizardStore.currentStep >= step.number ? 'text-primary-600' : 'text-gray-400'
            ]"
          >
            {{ step.label }}
          </span>
          <div 
            v-if="step.number < displaySteps.length"
            class="w-12 h-0.5 mx-2 bg-gray-200"
          />
        </div>
      </div>

      <form @submit.prevent="handleSubmit">
        <!-- Step 1: Demographics -->
        <div v-show="wizardStore.currentStep === 1">
          <BaseInputGrid layout="one-column">
            <BaseInputGrid>
              <BaseInputGroup :label="$t('patient_wizard.file_number')">
                <BaseInput
                  v-model="wizardStore.demographics.file_number"
                  type="text"
                  :placeholder="$t('patient_wizard.file_number_placeholder')"
                  @blur="checkFileNumber"
                />
                <span 
                  v-if="wizardStore.fileNumberTaken" 
                  class="text-sm text-red-500"
                >
                  {{ $t('patient_wizard.file_number_taken') }}
                </span>
              </BaseInputGroup>

              <BaseInputGroup :label="$t('patient_wizard.patient_name')" required>
                <BaseInput
                  v-model="wizardStore.demographics.name"
                  type="text"
                  required
                />
              </BaseInputGroup>
            </BaseInputGrid>

            <BaseInputGrid>
              <BaseInputGroup :label="$t('patient_wizard.sex')">
                <BaseMultiselect
                  v-model="wizardStore.demographics.gender"
                  :options="genderOptions"
                  value-prop="value"
                  label="label"
                  :allow-empty="true"
                />
              </BaseInputGroup>

              <BaseInputGroup :label="$t('patient_wizard.age')">
                <BaseInput
                  v-model.number="wizardStore.demographics.age"
                  type="number"
                  min="0"
                  max="150"
                />
              </BaseInputGroup>
            </BaseInputGrid>

            <BaseInputGroup :label="$t('patient_wizard.contact')">
              <BaseInput
                v-model="wizardStore.demographics.phone"
                type="tel"
              />
            </BaseInputGroup>

            <BaseInputGroup :label="$t('settings.currencies.currency')" required>
              <BaseMultiselect
                v-model="wizardStore.demographics.currency_id"
                :options="globalStore.currencies"
                value-prop="id"
                searchable
                :max-height="200"
                track-by="name"
                label="name"
              />
            </BaseInputGroup>
          </BaseInputGrid>
        </div>

        <!-- Step 2: Clinical Notes -->
        <div v-show="wizardStore.currentStep === 2">
          <BaseInputGrid layout="one-column">
            <BaseInputGroup :label="$t('patient_wizard.complaints')">
              <BaseMultiselect
                v-model="wizardStore.clinical.complaints"
                :options="dentalComplaints"
                mode="tags"
                searchable
                :create-option="true"
                :placeholder="$t('patient_wizard.select_complaints')"
              />
            </BaseInputGroup>

            <BaseInputGroup :label="$t('patient_wizard.diagnosis')">
              <!-- Dropdown to prefill diagnosis textarea -->
              <BaseMultiselect
                v-model="selectedDiagnosis"
                :options="dentalDiagnoses"
                searchable
                :placeholder="$t('patient_wizard.select_diagnosis')"
                class="mb-1"
                @change="prefillDiagnosis"
              />
              <BaseTextarea
                v-model="wizardStore.clinical.diagnosis"
                :placeholder="$t('patient_wizard.diagnosis_placeholder')"
                rows="2"
              />
            </BaseInputGroup>

            <BaseInputGroup :label="$t('patient_wizard.plan')">
              <BaseTextarea
                v-model="wizardStore.clinical.treatment_plan_notes"
                :placeholder="$t('patient_wizard.plan_placeholder')"
                rows="2"
              />
            </BaseInputGroup>

            <!-- Treatment Procedures Section -->
            <div class="mt-2">
              <label class="text-sm font-medium text-gray-700">
                {{ $t('patient_wizard.treatment') }}
              </label>
              
              <!-- Procedure search -->
              <BaseMultiselect
                v-model="selectedItem"
                :options="itemStore.items"
                value-prop="id"
                searchable
                :placeholder="$t('patient_wizard.add_procedure_placeholder')"
                track-by="name"
                label="name"
                class="mt-1"
                @change="addProcedure"
              >
                <!-- Add Item Action inside dropdown -->
                <template #action>
                  <BaseSelectAction @click.stop="openItemModal">
                    <BaseIcon
                      name="PlusCircleIcon"
                      class="h-4 mr-2 -ml-2 text-center text-primary-400"
                    />
                    {{ $t('general.add_new_item') }}
                  </BaseSelectAction>
                </template>
              </BaseMultiselect>

              <!-- Procedure list (editable) -->
              <div v-if="wizardStore.finances.pending_procedures.length > 0" class="border rounded-lg overflow-hidden mt-2">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">
                        {{ $t('patient_wizard.procedure_name') }}
                      </th>
                      <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 w-16">
                        {{ $t('patient_wizard.procedure_qty') }}
                      </th>
                      <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 w-24">
                        {{ $t('patient_wizard.procedure_price') }}
                      </th>
                      <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 w-24">
                        {{ $t('patient_wizard.procedure_total') }}
                      </th>
                      <th class="px-3 py-2 w-8"></th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr 
                      v-for="(proc, index) in wizardStore.finances.pending_procedures" 
                      :key="index"
                    >
                      <td class="px-3 py-2">
                        <div class="text-sm font-medium text-gray-900">{{ proc.name }}</div>
                        <input
                          v-model="proc.description"
                          type="text"
                          class="mt-1 text-xs text-gray-500 border-0 border-b border-dashed w-full focus:ring-0 p-0"
                          :placeholder="$t('patient_wizard.procedure_notes')"
                        />
                      </td>
                      <td class="px-3 py-2 text-center">
                        <input
                          v-model.number="proc.quantity"
                          type="number"
                          min="1"
                          class="w-14 text-center text-sm border rounded"
                        />
                      </td>
                      <td class="px-3 py-2 text-right">
                        <input
                          v-model.number="proc.price"
                          type="number"
                          min="0"
                          class="w-20 text-right text-sm border rounded"
                        />
                      </td>
                      <td class="px-3 py-2 text-right text-sm font-medium">
                        {{ formatMoney(proc.price * proc.quantity) }}
                      </td>
                      <td class="px-3 py-2">
                        <BaseIcon
                          name="XMarkIcon"
                          class="h-4 w-4 text-gray-400 hover:text-red-500 cursor-pointer"
                          @click="wizardStore.removeProcedure(index)"
                        />
                      </td>
                    </tr>
                  </tbody>
                  <tfoot class="bg-gray-50">
                    <tr>
                      <td colspan="3" class="px-3 py-2 text-right text-sm font-medium">
                        {{ $t('patient_wizard.total') }}:
                      </td>
                      <td class="px-3 py-2 text-right text-sm font-bold text-primary-600">
                        {{ formatMoney(wizardStore.billableTotal) }}
                      </td>
                      <td></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <div v-else class="text-center py-4 text-gray-400 text-sm border rounded-lg mt-2">
                {{ $t('patient_wizard.no_procedures') }}
              </div>
            </div>

            <BaseInputGroup :label="$t('patient_wizard.review_date')" class="mt-2">
              <BaseDatePicker
                v-model="wizardStore.clinical.review_date"
              />
            </BaseInputGroup>
          </BaseInputGrid>
        </div>

        <!-- Step 3: Summary (only for new patients) -->
        <div v-show="wizardStore.currentStep === 3 && !wizardStore.isEditMode">
          <h4 class="text-sm font-medium text-gray-700 mb-2">
            {{ $t('patient_wizard.summary') }}
          </h4>

          <!-- Read-only procedures summary -->
          <div v-if="wizardStore.finances.pending_procedures.length > 0" class="border rounded-lg overflow-hidden mb-4">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">
                    {{ $t('patient_wizard.procedure_name') }}
                  </th>
                  <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 w-16">
                    {{ $t('patient_wizard.procedure_qty') }}
                  </th>
                  <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 w-24">
                    {{ $t('patient_wizard.procedure_price') }}
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr 
                  v-for="(proc, index) in wizardStore.finances.pending_procedures" 
                  :key="index"
                >
                  <td class="px-4 py-2">
                    <div class="text-sm font-medium text-gray-900">{{ proc.name }}</div>
                    <div v-if="proc.description" class="text-xs text-gray-500">{{ proc.description }}</div>
                  </td>
                  <td class="px-4 py-2 text-center text-sm">
                    {{ proc.quantity }}
                  </td>
                  <td class="px-4 py-2 text-right text-sm">
                    {{ formatMoney(proc.price * proc.quantity) }}
                  </td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="2" class="px-4 py-2 text-right text-sm font-medium">
                    {{ $t('patient_wizard.total') }}:
                  </td>
                  <td class="px-4 py-2 text-right text-sm font-bold text-primary-600">
                    {{ formatMoney(wizardStore.billableTotal) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
          <div v-else class="text-center py-6 text-gray-400 border rounded-lg mb-4">
            {{ $t('patient_wizard.no_procedures') }}
            <p class="text-xs mt-1">{{ $t('patient_wizard.add_in_step_2') }}</p>
          </div>

          <!-- Payment method -->
          <BaseInputGroup :label="$t('patient_wizard.payment_method')">
            <BaseMultiselect
              v-model="wizardStore.finances.initial_payment_method"
              :options="paymentMethods"
              value-prop="name"
              label="name"
              :allow-empty="true"
            />
          </BaseInputGroup>
        </div>
      </form>
    </div>

    <!-- Footer with navigation -->
    <div class="z-0 flex justify-between p-4 border-t border-gray-200 border-solid">
      <BaseButton
        v-if="!wizardStore.isFirstStep"
        type="button"
        variant="primary-outline"
        @click="wizardStore.previousStep"
      >
        {{ $t('patient_wizard.back') }}
      </BaseButton>
      <div v-else></div>

      <div class="flex space-x-3">
        <BaseButton
          type="button"
          variant="primary-outline"
          @click="closeModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <BaseButton
          v-if="!wizardStore.isLastStep"
          type="button"
          variant="primary"
          :disabled="!wizardStore.isStepValid"
          @click="wizardStore.nextStep"
        >
          {{ $t('patient_wizard.next') }}
        </BaseButton>

        <template v-else>
          <BaseButton
            :loading="wizardStore.isLoading"
            variant="primary"
            type="button"
            @click="submitPatient(false)"
          >
            {{ $t('patient_wizard.save_patient') }}
          </BaseButton>

          <BaseButton
            v-if="!wizardStore.isEditMode && wizardStore.finances.pending_procedures.length > 0"
            :loading="wizardStore.isLoading"
            variant="primary"
            type="button"
            @click="submitPatient(true)"
          >
            {{ $t('patient_wizard.save_and_bill') }}
          </BaseButton>

          <BaseButton
            v-if="!wizardStore.isEditMode && wizardStore.finances.pending_procedures.length > 0"
            :loading="wizardStore.isLoading"
            variant="primary-outline"
            type="button"
            @click="submitAndQuotation"
          >
            {{ $t('patient_wizard.quotation') }}
          </BaseButton>
        </template>
      </div>
    </div>
  </BaseModal>
  
  <!-- ItemModal for inline item creation (rendered outside BaseModal so both can coexist) -->
  <ItemModal />
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

import { useModalStore } from '@/scripts/stores/modal'
import { usePatientWizardStore } from '@/scripts/admin/stores/patient-wizard'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useItemStore } from '@/scripts/admin/stores/item'
import { usePaymentStore } from '@/scripts/admin/stores/payment'
import ItemModal from '@/scripts/admin/components/modal-components/ItemModal.vue'

const { t } = useI18n()
const router = useRouter()
const modalStore = useModalStore()
const wizardStore = usePatientWizardStore()
const globalStore = useGlobalStore()
const companyStore = useCompanyStore()
const itemStore = useItemStore()
const paymentStore = usePaymentStore()

const selectedItem = ref(null)
const selectedDiagnosis = ref(null)

function prefillDiagnosis(value) {
  if (!value) return
  // Append selected diagnosis to existing text (or replace if empty)
  if (wizardStore.clinical.diagnosis && wizardStore.clinical.diagnosis.trim()) {
    wizardStore.clinical.diagnosis = wizardStore.clinical.diagnosis + ', ' + value
  } else {
    wizardStore.clinical.diagnosis = value
  }
  selectedDiagnosis.value = null
}

const modalActive = computed(
  () => modalStore.active && modalStore.componentName === 'PatientWizardModal'
)

const genderOptions = computed(() => [
  { value: 'Male', label: t('patient_wizard.male') },
  { value: 'Female', label: t('patient_wizard.female') },
])

// Pre-populated dental complaints for multi-select
const dentalComplaints = [
  'Toothache',
  'Tooth sensitivity',
  'Bleeding gums',
  'Swollen gums',
  'Bad breath',
  'Loose tooth',
  'Cracked/broken tooth',
  'Missing tooth',
  'Jaw pain',
  'Difficulty chewing',
  'Tooth discoloration',
  'Mouth sores',
  'Dry mouth',
  'Gum recession',
  'Tooth decay',
  'Wisdom tooth pain',
  'Teeth grinding',
  'Clicking jaw',
  'Mouth ulcers',
  'Burning mouth',
  'Dental abscess',
  'Food impaction',
  'Denture problems',
  'Orthodontic issues',
  'Routine checkup',
]

// Pre-populated dental diagnoses for multi-select
const dentalDiagnoses = [
  'Dental caries',
  'Gingivitis',
  'Periodontitis',
  'Pulpitis',
  'Dental abscess',
  'Periapical abscess',
  'Tooth fracture',
  'Dental erosion',
  'Tooth attrition',
  'Tooth abrasion',
  'Impacted tooth',
  'Malocclusion',
  'TMJ disorder',
  'Bruxism',
  'Oral candidiasis',
  'Aphthous ulcer',
  'Leukoplakia',
  'Lichen planus',
  'Root resorption',
  'Tooth avulsion',
  'Tooth luxation',
  'Alveolar osteitis (dry socket)',
  'Pericoronitis',
  'Dental fluorosis',
  'Tooth hypersensitivity',
  'Cracked tooth syndrome',
  'Gingival hyperplasia',
  'Oral mucositis',
  'Angular cheilitis',
  'Healthy dentition',
]

const displaySteps = computed(() => {
  const steps = [
    { number: 1, label: t('patient_wizard.step_demographics') },
    { number: 2, label: t('patient_wizard.step_clinical') },
  ]
  if (!wizardStore.isEditMode) {
    steps.push({ number: 3, label: t('patient_wizard.step_finances') })
  }
  return steps
})

const paymentMethods = computed(() => {
  return paymentStore.paymentModes || []
})

function formatMoney(amount) {
  const currency = globalStore.currencies.find(
    c => c.id === wizardStore.demographics.currency_id
  )
  if (!currency) return amount
  return `${currency.symbol}${(amount / 100).toLocaleString()}`
}

async function initializeWizard() {
  // Skip reset if returning from ItemModal (preserves wizard state)
  if (modalStore.data?.skipReset) {
    return
  }
  
  // Reset to clean state
  wizardStore.resetWizard()
  
  // Set default currency
  wizardStore.demographics.currency_id = companyStore.selectedCompanyCurrency?.id
  
  // Ensure currencies are loaded for the dropdown
  if (!globalStore.currencies || globalStore.currencies.length === 0) {
    await globalStore.fetchCurrencies()
  }
  
  // Ensure items are loaded for procedure selection
  if (!itemStore.items || itemStore.items.length === 0) {
    await itemStore.fetchItems({ limit: 'all' })
  }
  
  // Ensure payment modes are loaded
  if (!paymentStore.paymentModes || paymentStore.paymentModes.length === 0) {
    await paymentStore.fetchPaymentModes()
  }
  
  // Check if editing an existing patient
  if (modalStore.id) {
    // Load patient data would be handled here
    // For now, we assume modal.data contains patient info
    if (modalStore.data) {
      wizardStore.loadPatient(modalStore.data)
    }
  }
  // No draft loading - always start fresh for new patients
}

function checkFileNumber() {
  wizardStore.checkFileNumber(wizardStore.demographics.file_number)
}

function addProcedure(itemId) {
  if (!itemId) return
  const item = itemStore.items.find(i => i.id === itemId)
  if (item) {
    wizardStore.addProcedure(item)
  }
  selectedItem.value = null
}

function openItemModal() {
  // Remember current step before opening ItemModal
  const currentStep = wizardStore.currentStep
  
  // Open ItemModal (PatientWizardModal will close, but state is preserved in Pinia store)
  modalStore.openModal({
    title: t('items.add_item'),
    componentName: 'ItemModal',
    data: true, // Required for ItemModal to call refreshData callback
    refreshData: async (val) => {
      // Refresh items list
      await itemStore.fetchItems({ limit: 'all' })
      
      // Add the new item as a procedure if available
      if (val && val.id) {
        wizardStore.addProcedure(val)
      }
      
      // Re-open PatientWizardModal with preserved state
      setTimeout(() => {
        modalStore.openModal({
          title: t('patient_wizard.title'),
          componentName: 'PatientWizardModal',
          data: { skipReset: true }, // Preserve wizard state
        })
        // Restore the step we were on
        wizardStore.currentStep = currentStep
      }, 100)
    },
  })
}

async function submitPatient(andBill) {
  const result = await wizardStore.submitPatient(andBill)
  
  if (result.success) {
    closeModal()
    
    // If "Save & Bill", redirect to invoice creation with customer pre-selected
    if (result.andBill && result.patient) {
      router.push({
        name: 'invoices.create',
        query: { customer: result.patient.id }
      })
    }
  }
}

async function submitAndQuotation() {
  const result = await wizardStore.submitPatient(false)
  
  if (result.success) {
    closeModal()
    
    // Redirect to quotation (estimate) creation with customer pre-selected
    if (result.patient) {
      router.push({
        name: 'estimates.create',
        query: { customer: result.patient.id }
      })
    }
  }
}

function closeModal() {
  modalStore.closeModal()
  setTimeout(() => {
    wizardStore.resetWizard()
  }, 300)
}
</script>
