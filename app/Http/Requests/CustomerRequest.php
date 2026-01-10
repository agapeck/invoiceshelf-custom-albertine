<?php

namespace App\Http\Requests;

use App\Models\Address;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
            ],
            'email' => [
                'email',
                'nullable',
                Rule::unique('customers')->where('company_id', $this->header('company')),
            ],
            'password' => [
                'nullable',
            ],
            'phone' => [
                'nullable',
            ],
            'company_name' => [
                'nullable',
            ],
            'contact_name' => [
                'nullable',
            ],
            'website' => [
                'nullable',
            ],
            'prefix' => [
                'nullable',
            ],
            'tax_id' => [
                'nullable',
            ],
            'enable_portal' => [
                'boolean',
            ],
            'currency_id' => [
                'nullable',
            ],
            'billing.name' => [
                'nullable',
            ],
            'billing.address_street_1' => [
                'nullable',
            ],
            'billing.address_street_2' => [
                'nullable',
            ],
            'billing.city' => [
                'nullable',
            ],
            'billing.state' => [
                'nullable',
            ],
            'billing.country_id' => [
                'nullable',
            ],
            'billing.zip' => [
                'nullable',
            ],
            'billing.phone' => [
                'nullable',
            ],
            'billing.fax' => [
                'nullable',
            ],
            'shipping.name' => [
                'nullable',
            ],
            'shipping.address_street_1' => [
                'nullable',
            ],
            'shipping.address_street_2' => [
                'nullable',
            ],
            'shipping.city' => [
                'nullable',
            ],
            'shipping.state' => [
                'nullable',
            ],
            'shipping.country_id' => [
                'nullable',
            ],
            'shipping.zip' => [
                'nullable',
            ],
            'shipping.phone' => [
                'nullable',
            ],
            'shipping.fax' => [
                'nullable',
            ],
            
            // Patient/Dental fields
            'file_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('customers')
                    ->where('company_id', $this->header('company'))
                    ->ignore($this->route('customer')?->id),
            ],
            'gender' => ['nullable', 'string', 'in:Male,Female'],
            'age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'next_of_kin' => ['nullable', 'string', 'max:255'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:50'],
            'attended_to_by' => ['nullable', 'string', 'max:255'],
            'complaints' => ['nullable', 'string', 'max:65000'],
            'diagnosis' => ['nullable', 'string', 'max:65000'],
            'treatment' => ['nullable', 'string', 'max:65000'],
            'treatment_plan_notes' => ['nullable', 'string', 'max:65000'],
            'review_date' => ['nullable', 'date'],
            
            // JSON pending procedures (validated as array, stored as JSON)
            'pending_procedures' => ['nullable', 'array'],
            'pending_procedures.*.item_id' => ['required_with:pending_procedures', 'exists:items,id'],
            'pending_procedures.*.name' => ['required_with:pending_procedures', 'string'],
            'pending_procedures.*.quantity' => ['required_with:pending_procedures', 'integer', 'min:1'],
            'pending_procedures.*.price' => ['required_with:pending_procedures', 'integer', 'min:0'],
            'pending_procedures.*.description' => ['nullable', 'string'],
            
            'initial_payment_method' => ['nullable', 'string'],
        ];

        if ($this->isMethod('PUT') && $this->email != null) {
            $rules['email'] = [
                'email',
                'nullable',
                Rule::unique('customers')->where('company_id', $this->header('company'))->ignore($this->route('customer')->id),
            ];
        }

        return $rules;
    }

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
                // Patient/Dental fields
                'file_number',
                'gender',
                'age',
                'next_of_kin',
                'next_of_kin_phone',
                'attended_to_by',
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

    public function getShippingAddress()
    {
        return collect($this->shipping)
            ->merge([
                'type' => Address::SHIPPING_TYPE,
            ])
            ->toArray();
    }

    public function getBillingAddress()
    {
        return collect($this->billing)
            ->merge([
                'type' => Address::BILLING_TYPE,
            ])
            ->toArray();
    }

    public function hasAddress(array $address)
    {
        $data = Arr::where($address, function ($value, $key) {
            return isset($value);
        });

        return $data;
    }
}
