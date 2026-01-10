<?php

// database/migrations/2025_12_22_150000_add_dental_patient_fields_to_customers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add dental clinic patient fields to customers table.
     * 
     * New fields:
     * - file_number: Unique patient file number (e.g., ADS-001)
     * - gender: Male/Female
     * - complaints: Chief complaint text
     * - treatment_plan_notes: Plan notes (separate from treatment procedures)
     * - pending_procedures: JSON array for handoff pattern (procedures waiting to be billed)
     * - initial_payment_method: Reference to payment method used
     * - initial_invoice_id: Reference to first invoice created
     */
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
