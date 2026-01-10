<?php

namespace App\Http\Controllers\V1\Admin\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PatientWizardController extends Controller
{
    /**
     * Store a newly created patient from the wizard.
     * 
     * This endpoint handles the multi-step patient wizard form submission.
     * It creates a customer with all patient fields including pending_procedures.
     */
    public function store(CustomerRequest $request)
    {
        $this->authorize('create', Customer::class);

        $customer = Customer::createCustomer($request);

        // Check for creation errors (like duplicate email)
        if (is_array($customer) && isset($customer['error'])) {
            return response()->json([
                'error' => $customer['error'],
                'message' => $customer['message'],
            ], 422);
        }

        return new CustomerResource($customer);
    }

    /**
     * Get validated company ID from header.
     * 
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function getValidatedCompanyId(Request $request): int
    {
        $companyId = (int) $request->header('company');
        
        // Ensure company ID is valid and user belongs to this company
        if (!$companyId || !$request->user()->hasCompany($companyId)) {
            abort(403, 'Unauthorized access to company');
        }
        
        return $companyId;
    }

    /**
     * Generate cache key for patient wizard draft.
     */
    private function getDraftCacheKey(int $companyId, int $userId): string
    {
        return "patient_wizard_draft_{$companyId}_{$userId}";
    }

    /**
     * Save wizard draft to server-side cache.
     * 
     * Drafts are stored per-user per-company and auto-expire after 24 hours.
     */
    public function saveDraft(Request $request)
    {
        // Authorization: user must be able to create customers
        $this->authorize('create', Customer::class);
        
        $userId = $request->user()->id;
        $companyId = $this->getValidatedCompanyId($request);
        $cacheKey = $this->getDraftCacheKey($companyId, $userId);

        $draft = $request->validate([
            'demographics' => 'required|array',
            'demographics.name' => 'nullable|string|max:255',
            'demographics.file_number' => 'nullable|string|max:50',
            'demographics.gender' => 'nullable|in:Male,Female',
            'demographics.age' => 'nullable|integer|min:0|max:150',
            'demographics.phone' => 'nullable|string|max:50',
            'demographics.currency_id' => 'nullable|integer',
            'clinical' => 'nullable|array',
            'clinical.complaints' => 'nullable|string|max:10000',
            'clinical.diagnosis' => 'nullable|string|max:10000',
            'clinical.treatment' => 'nullable|string|max:10000',
            'clinical.treatment_plan_notes' => 'nullable|string|max:10000',
            'clinical.review_date' => 'nullable|date',
            'finances' => 'nullable|array',
            'finances.pending_procedures' => 'nullable|array',
            'finances.pending_procedures.*.item_id' => 'nullable|integer',
            'finances.pending_procedures.*.name' => 'nullable|string|max:255',
            'finances.pending_procedures.*.price' => 'nullable|integer|min:0',
            'finances.pending_procedures.*.quantity' => 'nullable|integer|min:1',
            'currentStep' => 'required|integer|min:1|max:3',
        ]);

        // Store draft for 24 hours
        Cache::put($cacheKey, $draft, now()->addHours(24));

        return response()->json([
            'success' => true,
            'message' => 'Draft saved successfully',
            'savedAt' => now()->toISOString(),
        ]);
    }

    /**
     * Get wizard draft from server-side cache.
     */
    public function getDraft(Request $request)
    {
        // Authorization: user must be able to create customers
        $this->authorize('create', Customer::class);
        
        $userId = $request->user()->id;
        $companyId = $this->getValidatedCompanyId($request);
        $cacheKey = $this->getDraftCacheKey($companyId, $userId);

        $draft = Cache::get($cacheKey);

        if (!$draft) {
            return response()->json([
                'exists' => false,
                'draft' => null,
            ]);
        }

        return response()->json([
            'exists' => true,
            'draft' => $draft,
        ]);
    }

    /**
     * Clear wizard draft from server-side cache.
     */
    public function clearDraft(Request $request)
    {
        // Authorization: user must be able to create customers
        $this->authorize('create', Customer::class);
        
        $userId = $request->user()->id;
        $companyId = $this->getValidatedCompanyId($request);
        $cacheKey = $this->getDraftCacheKey($companyId, $userId);

        Cache::forget($cacheKey);

        return response()->json([
            'success' => true,
            'message' => 'Draft cleared successfully',
        ]);
    }

    /**
     * Check if a file number is already in use.
     * 
     * Used for async validation in the patient wizard form.
     */
    public function checkFileNumber(Request $request)
    {
        // Validate all inputs with proper types
        $validated = $request->validate([
            'number' => 'required|string|max:50',
            'exclude_id' => 'nullable|integer',
        ]);
        
        $companyId = $this->getValidatedCompanyId($request);
        
        $exists = Customer::where('company_id', $companyId)
            ->where('file_number', $validated['number'])
            ->when(isset($validated['exclude_id']), function ($query) use ($validated) {
                return $query->where('id', '!=', $validated['exclude_id']);
            })
            ->exists();
        
        return response()->json(['exists' => $exists]);
    }
}
