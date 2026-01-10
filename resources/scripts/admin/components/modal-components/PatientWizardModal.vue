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
              <BaseTextarea
                v-model="wizardStore.clinical.complaints"
                :placeholder="$t('patient_wizard.complaints_placeholder')"
                rows="3"
              />
            </BaseInputGroup>

            <BaseInputGroup :label="$t('patient_wizard.diagnosis')">
              <BaseTextarea
                v-model="wizardStore.clinical.diagnosis"
                :placeholder="$t('patient_wizard.diagnosis_placeholder')"
                rows="3"
              />
            </BaseInputGroup>

            <BaseInputGroup :label="$t('patient_wizard.plan')">
              <BaseTextarea
                v-model="wizardStore.clinical.treatment_plan_notes"
                :placeholder="$t('patient_wizard.plan_placeholder')"
                rows="3"
              />
            </BaseInputGroup>

            <BaseInputGroup :label="$t('patient_wizard.review_date')">
              <BaseDatePicker
                v-model="wizardStore.clinical.review_date"
              />
            </BaseInputGroup>
          </BaseInputGrid>
        </div>

        <!-- Step 3: Finances (only for new patients) -->
        <div v-show="wizardStore.currentStep === 3 && !wizardStore.isEditMode">
          <div class="mb-4">
            <label class="text-sm font-medium text-gray-700">
              {{ $t('patient_wizard.procedures') }}
            </label>
            
            <!-- Procedure search -->
            <BaseMultiselect
              v-model="selectedItem"
              :options="globalStore.items"
              value-prop="id"
              searchable
              :placeholder="$t('patient_wizard.procedures_placeholder')"
              track-by="name"
              label="name"
              class="mt-1"
              @change="addProcedure"
            />
          </div>

          <!-- Pending procedures list -->
          <div v-if="wizardStore.finances.pending_procedures.length > 0" class="border rounded-lg overflow-hidden mb-4">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">
                    {{ $t('patient_wizard.procedure_name') }}
                  </th>
                  <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 w-20">
                    {{ $t('patient_wizard.procedure_qty') }}
                  </th>
                  <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 w-24">
                    {{ $t('patient_wizard.procedure_price') }}
                  </th>
                  <th class="px-4 py-2 w-10"></th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr 
                  v-for="(proc, index) in wizardStore.finances.pending_procedures" 
                  :key="index"
                >
                  <td class="px-4 py-2">
                    <div class="text-sm font-medium text-gray-900">{{ proc.name }}</div>
                    <input
                      v-model="proc.description"
                      type="text"
                      class="mt-1 text-xs text-gray-500 border-0 border-b border-dashed w-full focus:ring-0 p-0"
                      :placeholder="$t('patient_wizard.procedure_notes')"
                    />
                  </td>
                  <td class="px-4 py-2 text-center">
                    <input
                      v-model.number="proc.quantity"
                      type="number"
                      min="1"
                      class="w-16 text-center text-sm border rounded"
                    />
                  </td>
                  <td class="px-4 py-2 text-right text-sm">
                    {{ formatMoney(proc.price * proc.quantity) }}
                  </td>
                  <td class="px-4 py-2">
                    <BaseIcon
                      name="XMarkIcon"
                      class="h-5 w-5 text-gray-400 hover:text-red-500 cursor-pointer"
                      @click="wizardStore.removeProcedure(index)"
                    />
                  </td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr>
                  <td colspan="2" class="px-4 py-2 text-right text-sm font-medium">
                    {{ $t('patient_wizard.billable_amount') }}:
                  </td>
                  <td class="px-4 py-2 text-right text-sm font-bold text-primary-600">
                    {{ formatMoney(wizardStore.billableTotal) }}
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <div v-else class="text-center py-8 text-gray-400">
            {{ $t('patient_wizard.no_procedures_selected') }}
            <p class="text-xs mt-1">{{ $t('patient_wizard.add_procedure_hint') }}</p>
          </div>

          <!-- Payment method -->
          <BaseInputGroup :label="$t('patient_wizard.payment_method')" class="mt-4">
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
        </template>
      </div>
    </div>
  </BaseModal>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

import { useModalStore } from '@/scripts/stores/modal'
import { usePatientWizardStore } from '@/scripts/admin/stores/patient-wizard'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useCompanyStore } from '@/scripts/admin/stores/company'

const { t } = useI18n()
const router = useRouter()
const modalStore = useModalStore()
const wizardStore = usePatientWizardStore()
const globalStore = useGlobalStore()
const companyStore = useCompanyStore()

const selectedItem = ref(null)

const modalActive = computed(
  () => modalStore.active && modalStore.componentName === 'PatientWizardModal'
)

const genderOptions = computed(() => [
  { value: 'Male', label: t('patient_wizard.male') },
  { value: 'Female', label: t('patient_wizard.female') },
])

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
  return companyStore.selectedCompany?.payment_methods || []
})

function formatMoney(amount) {
  const currency = globalStore.currencies.find(
    c => c.id === wizardStore.demographics.currency_id
  )
  if (!currency) return amount
  return `${currency.symbol}${(amount / 100).toLocaleString()}`
}

async function initializeWizard() {
  // Reset to clean state
  wizardStore.resetWizard()
  
  // Set default currency
  wizardStore.demographics.currency_id = companyStore.selectedCompanyCurrency?.id
  
  // Ensure items are loaded for procedure selection
  if (!globalStore.items || globalStore.items.length === 0) {
    await globalStore.fetchItems()
  }
  
  // Check if editing an existing patient
  if (modalStore.id) {
    // Load patient data would be handled here
    // For now, we assume modal.data contains patient info
    if (modalStore.data) {
      wizardStore.loadPatient(modalStore.data)
    }
  } else {
    // Check for existing draft
    const draft = await wizardStore.loadDraft()
    if (draft) {
      // Could show a dialog here asking to continue draft
      wizardStore.applyDraft(draft)
    }
  }
}

function checkFileNumber() {
  wizardStore.checkFileNumber(wizardStore.demographics.file_number)
}

function addProcedure(itemId) {
  if (!itemId) return
  const item = globalStore.items.find(i => i.id === itemId)
  if (item) {
    wizardStore.addProcedure(item)
  }
  selectedItem.value = null
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

function closeModal() {
  // Auto-save draft when closing without submitting
  if (wizardStore.demographics.name && !wizardStore.isEditMode) {
    wizardStore.saveDraft()
  }
  
  modalStore.closeModal()
  setTimeout(() => {
    wizardStore.resetWizard()
  }, 300)
}
</script>
