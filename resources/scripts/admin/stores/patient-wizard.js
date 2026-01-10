import axios from 'axios'
import { defineStore } from 'pinia'
import { handleError } from '@/scripts/helpers/error-handling'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useCustomerStore } from '@/scripts/admin/stores/customer'

export const usePatientWizardStore = (useWindow = false) => {
    const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
    const { global } = window.i18n

    return defineStoreFunc({
        id: 'patient-wizard',

        state: () => ({
            isLoading: false,
            currentStep: 1,
            totalSteps: 3,
            isEditMode: false,

            // Form data organized by step
            demographics: {
                file_number: '',
                name: '',
                gender: null,
                age: null,
                phone: '',
                currency_id: null,
            },

            clinical: {
                complaints: '',
                diagnosis: '',
                treatment: '',
                treatment_plan_notes: '',
                review_date: null,
            },

            finances: {
                pending_procedures: [],
                initial_payment_method: null,
            },

            // Validation state
            fileNumberTaken: false,
            fileNumberChecking: false,
        }),

        getters: {
            isFirstStep: (state) => state.currentStep === 1,
            isLastStep: (state) => state.currentStep === state.totalSteps,

            // Calculate billable total from pending procedures
            billableTotal: (state) => {
                if (!state.finances.pending_procedures) return 0
                return state.finances.pending_procedures.reduce((sum, proc) => {
                    return sum + (proc.price || 0) * (proc.quantity || 1)
                }, 0)
            },

            // Check if all required fields in current step are filled
            isStepValid: (state) => {
                switch (state.currentStep) {
                    case 1: // Demographics
                        return state.demographics.name &&
                            state.demographics.currency_id &&
                            !state.fileNumberTaken
                    case 2: // Clinical (optional)
                        return true
                    case 3: // Finances (optional)
                        return true
                    default:
                        return true
                }
            },

            // Compile patient data for submission
            patientData: (state) => {
                const companyStore = useCompanyStore()
                return {
                    ...state.demographics,
                    ...state.clinical,
                    pending_procedures: state.finances.pending_procedures,
                    initial_payment_method: state.finances.initial_payment_method,
                    company_id: companyStore.selectedCompany?.id,
                }
            },
        },

        actions: {
            resetWizard() {
                this.currentStep = 1
                this.totalSteps = 3  // Reset to 3 steps for new patients
                this.isEditMode = false
                this.demographics = {
                    file_number: '',
                    name: '',
                    gender: null,
                    age: null,
                    phone: '',
                    currency_id: null,
                }
                this.clinical = {
                    complaints: '',
                    diagnosis: '',
                    treatment: '',
                    treatment_plan_notes: '',
                    review_date: null,
                }
                this.finances = {
                    pending_procedures: [],
                    initial_payment_method: null,
                }
                this.fileNumberTaken = false
                this.fileNumberChecking = false
            },

            nextStep() {
                if (this.currentStep < this.totalSteps) {
                    this.currentStep++
                }
            },

            previousStep() {
                if (this.currentStep > 1) {
                    this.currentStep--
                }
            },

            goToStep(step) {
                if (step >= 1 && step <= this.totalSteps) {
                    this.currentStep = step
                }
            },

            // Load existing patient data for editing
            loadPatient(patient) {
                this.isEditMode = true
                this.totalSteps = 2 // Hide finances in edit mode

                this.demographics = {
                    id: patient.id,
                    file_number: patient.file_number || '',
                    name: patient.name || '',
                    gender: patient.gender || null,
                    age: patient.age || null,
                    phone: patient.phone || '',
                    currency_id: patient.currency_id,
                }

                this.clinical = {
                    complaints: patient.complaints || '',
                    diagnosis: patient.diagnosis || '',
                    treatment: patient.treatment || '',
                    treatment_plan_notes: patient.treatment_plan_notes || '',
                    review_date: patient.review_date || null,
                }
            },

            // Add a procedure to pending list
            addProcedure(item) {
                const exists = this.finances.pending_procedures.find(
                    p => p.item_id === item.id
                )
                if (!exists) {
                    this.finances.pending_procedures.push({
                        item_id: item.id,
                        name: item.name,
                        description: item.description || '',
                        price: item.price,
                        quantity: 1,
                    })
                }
            },

            // Remove a procedure from pending list
            removeProcedure(index) {
                this.finances.pending_procedures.splice(index, 1)
            },

            // Update procedure quantity
            updateProcedureQuantity(index, quantity) {
                if (quantity >= 1) {
                    this.finances.pending_procedures[index].quantity = quantity
                }
            },

            // Update procedure description (for clinical notes)
            updateProcedureDescription(index, description) {
                this.finances.pending_procedures[index].description = description
            },

            // Check if file number is available
            async checkFileNumber(number) {
                if (!number) {
                    this.fileNumberTaken = false
                    return
                }

                this.fileNumberChecking = true
                try {
                    const response = await axios.get('/api/v1/patients/check-file-number', {
                        params: {
                            number,
                            exclude_id: this.demographics.id || null
                        }
                    })
                    this.fileNumberTaken = response.data.exists
                } catch (err) {
                    handleError(err)
                } finally {
                    this.fileNumberChecking = false
                }
            },

            // Save draft to server
            async saveDraft() {
                try {
                    await axios.post('/api/v1/patients/wizard/draft', {
                        demographics: this.demographics,
                        clinical: this.clinical,
                        finances: this.finances,
                        currentStep: this.currentStep,
                    })
                } catch (err) {
                    // Silently fail for drafts
                    console.error('Draft save failed:', err)
                }
            },

            // Load draft from server
            async loadDraft() {
                try {
                    const response = await axios.get('/api/v1/patients/wizard/draft')
                    if (response.data.exists) {
                        return response.data.draft
                    }
                } catch (err) {
                    console.error('Draft load failed:', err)
                }
                return null
            },

            // Clear draft from server
            async clearDraft() {
                try {
                    await axios.delete('/api/v1/patients/wizard/draft')
                } catch (err) {
                    console.error('Draft clear failed:', err)
                }
            },

            // Apply draft data to wizard state
            applyDraft(draft) {
                if (draft.demographics) this.demographics = { ...this.demographics, ...draft.demographics }
                if (draft.clinical) this.clinical = { ...this.clinical, ...draft.clinical }
                if (draft.finances) this.finances = { ...this.finances, ...draft.finances }
                if (draft.currentStep) this.currentStep = draft.currentStep
            },

            // Submit patient data
            async submitPatient(andBill = false) {
                const customerStore = useCustomerStore()
                const notificationStore = useNotificationStore()

                this.isLoading = true

                try {
                    let response
                    if (this.isEditMode) {
                        // Update existing patient
                        response = await axios.put(
                            `/api/v1/customers/${this.demographics.id}`,
                            this.patientData
                        )
                        notificationStore.showNotification({
                            type: 'success',
                            message: global.t('patient_wizard.patient_updated'),
                        })
                    } else {
                        // Create new patient
                        response = await axios.post('/api/v1/patients/wizard', this.patientData)
                        notificationStore.showNotification({
                            type: 'success',
                            message: global.t('patient_wizard.patient_saved'),
                        })
                    }

                    // Clear draft on successful save
                    await this.clearDraft()

                    // Return the new patient data with flag for billing redirect
                    return {
                        success: true,
                        patient: response.data.data,
                        andBill: andBill,
                    }
                } catch (err) {
                    handleError(err)
                    return { success: false }
                } finally {
                    this.isLoading = false
                }
            },
        },
    })()
}
