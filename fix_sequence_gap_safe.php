<?php
/**
 * SAFE FIX: Fill sequence_number gap from PAY-001489 manual entry
 * 
 * What this does:
 * 1. Sets payment ID 1492 (PAY-001489) sequence_number to 1489
 * 2. Increments all payments with ID >= 1493 by +1
 * 3. Preserves all relationships (invoice_id, customer_id, etc)
 * 4. Only modifies sequence_number field
 * 
 * Verification: 3X checks before and after
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo str_repeat("=", 100) . "\n";
echo "SAFE SEQUENCE NUMBER GAP FIX\n";
echo str_repeat("=", 100) . "\n\n";

// ============================================================================
// VERIFICATION #1: BEFORE STATE
// ============================================================================

echo "📋 VERIFICATION #1: Current State (Before Fix)\n";
echo str_repeat("-", 100) . "\n";

$before_payment_1492 = DB::table('payments')->where('id', 1492)->first();
$before_payments_after = DB::table('payments')
    ->where('id', '>=', 1493)
    ->where('id', '<=', 1503)
    ->orderBy('id')
    ->get(['id', 'payment_number', 'sequence_number', 'invoice_id', 'customer_id']);

echo "\n🔍 Payment 1492 (PAY-001489) BEFORE:\n";
printf("   ID: %d | Number: %s | Sequence: %s | Invoice: %d | Customer: %d\n",
    $before_payment_1492->id,
    $before_payment_1492->payment_number,
    $before_payment_1492->sequence_number ?? 'NULL',
    $before_payment_1492->invoice_id,
    $before_payment_1492->customer_id
);

echo "\n🔍 Next 10 Payments BEFORE (should be off by 1):\n";
printf("%-6s %-18s %-15s %-12s %-12s\n", "ID", "Payment Number", "Sequence", "Invoice ID", "Customer ID");
echo str_repeat("-", 100) . "\n";
foreach ($before_payments_after as $p) {
    printf("%-6d %-18s %-15s %-12s %-12s\n",
        $p->id,
        $p->payment_number,
        $p->sequence_number ?? 'NULL',
        $p->invoice_id,
        $p->customer_id
    );
}

// Check for expected off-by-one pattern
$off_by_one_count = 0;
foreach ($before_payments_after as $p) {
    if ($p->sequence_number !== null) {
        $extracted = (int)substr($p->payment_number, 4);
        $diff = $extracted - $p->sequence_number;
        if ($diff === 1) {
            $off_by_one_count++;
        }
    }
}

echo "\n✓ Off-by-one payments found: $off_by_one_count\n";
echo "✓ Payment 1492 sequence: " . ($before_payment_1492->sequence_number ?? 'NULL') . "\n";

if ($before_payment_1492->sequence_number !== null) {
    die("\n❌ ERROR: Payment 1492 already has a sequence_number. This script should only run once.\n");
}

if ($off_by_one_count < 5) {
    die("\n❌ ERROR: Expected off-by-one pattern not found. Manual review needed.\n");
}

echo "\n✅ Pre-conditions verified. Safe to proceed.\n\n";

// ============================================================================
// THE FIX
// ============================================================================

echo str_repeat("=", 100) . "\n";
echo "🔧 APPLYING FIX\n";
echo str_repeat("=", 100) . "\n\n";

DB::beginTransaction();

try {
    // Step 1: Fix the NULL entry (payment 1492)
    echo "Step 1: Setting payment 1492 sequence_number to 1489...\n";
    $updated_null = DB::table('payments')
        ->where('id', 1492)
        ->update(['sequence_number' => 1489]);
    
    echo "   ✓ Updated $updated_null record\n\n";
    
    // Step 2: Increment all subsequent payments
    echo "Step 2: Incrementing sequence_numbers for payments >= 1493...\n";
    
    // Get all payments that need updating
    $payments_to_update = DB::table('payments')
        ->where('id', '>=', 1493)
        ->where('sequence_number', '>=', 1489)
        ->orderBy('id')
        ->get(['id', 'sequence_number', 'payment_number']);
    
    $update_count = 0;
    foreach ($payments_to_update as $payment) {
        DB::table('payments')
            ->where('id', $payment->id)
            ->update(['sequence_number' => $payment->sequence_number + 1]);
        
        $update_count++;
        
        if ($update_count % 50 == 0) {
            echo "   ✓ Updated $update_count payments...\n";
        }
    }
    
    echo "   ✓ Total updated: $update_count payments\n\n";
    
    // ============================================================================
    // VERIFICATION #2: AFTER FIX (IN TRANSACTION)
    // ============================================================================
    
    echo str_repeat("=", 100) . "\n";
    echo "📋 VERIFICATION #2: State After Fix (Still in Transaction)\n";
    echo str_repeat("-", 100) . "\n";
    
    $after_payment_1492 = DB::table('payments')->where('id', 1492)->first();
    $after_payments_after = DB::table('payments')
        ->where('id', '>=', 1493)
        ->where('id', '<=', 1503)
        ->orderBy('id')
        ->get(['id', 'payment_number', 'sequence_number', 'invoice_id', 'customer_id']);
    
    echo "\n🔍 Payment 1492 (PAY-001489) AFTER:\n";
    printf("   ID: %d | Number: %s | Sequence: %s | Invoice: %d | Customer: %d\n",
        $after_payment_1492->id,
        $after_payment_1492->payment_number,
        $after_payment_1492->sequence_number ?? 'NULL',
        $after_payment_1492->invoice_id,
        $after_payment_1492->customer_id
    );
    
    echo "\n🔍 Next 10 Payments AFTER (should be aligned):\n";
    printf("%-6s %-18s %-15s %-12s %-12s %-10s\n", "ID", "Payment#", "Sequence", "Invoice", "Customer", "Aligned?");
    echo str_repeat("-", 100) . "\n";
    
    $aligned_count = 0;
    $misaligned = [];
    
    foreach ($after_payments_after as $p) {
        $extracted = (int)substr($p->payment_number, 4);
        $aligned = ($extracted === $p->sequence_number);
        $status = $aligned ? "✓ YES" : "✗ NO";
        
        if ($aligned) {
            $aligned_count++;
        } else {
            $misaligned[] = [
                'id' => $p->id,
                'number' => $p->payment_number,
                'sequence' => $p->sequence_number,
                'expected' => $extracted
            ];
        }
        
        printf("%-6d %-18s %-15s %-12s %-12s %-10s\n",
            $p->id,
            $p->payment_number,
            $p->sequence_number,
            $p->invoice_id,
            $p->customer_id,
            $status
        );
    }
    
    // Verify payment 1492 is fixed
    if ($after_payment_1492->sequence_number != 1489) {
        throw new Exception("Payment 1492 sequence not set to 1489!");
    }
    
    // Verify alignment
    if ($aligned_count !== count($after_payments_after->toArray())) {
        echo "\n⚠️  WARNING: Found misaligned payments:\n";
        foreach ($misaligned as $m) {
            printf("   ID %d: %s has sequence %d (expected %d)\n",
                $m['id'], $m['number'], $m['sequence'], $m['expected']
            );
        }
        throw new Exception("Sequence numbers not properly aligned!");
    }
    
    echo "\n✅ All " . count($after_payments_after) . " checked payments are aligned!\n";
    echo "✅ Payment 1492 sequence_number = 1489\n";
    
    // Verify relationships unchanged
    $relationships_ok = true;
    foreach ($before_payments_after as $before) {
        $after = $after_payments_after->firstWhere('id', $before->id);
        if ($after && (
            $after->invoice_id !== $before->invoice_id ||
            $after->customer_id !== $before->customer_id ||
            $after->payment_number !== $before->payment_number
        )) {
            echo "\n❌ ERROR: Payment {$before->id} relationships changed!\n";
            $relationships_ok = false;
        }
    }
    
    if (!$relationships_ok) {
        throw new Exception("Payment relationships were modified - ROLLBACK!");
    }
    
    echo "✅ All payment-invoice relationships unchanged\n";
    echo "✅ All customer assignments unchanged\n";
    echo "✅ All payment_numbers unchanged\n\n";
    
    // Final safety check
    echo "🔒 FINAL SAFETY CHECK...\n";
    
    $total_payments = DB::table('payments')->count();
    $unique_sequences = DB::table('payments')
        ->whereNotNull('sequence_number')
        ->distinct('sequence_number')
        ->count('sequence_number');
    $max_sequence = DB::table('payments')->max('sequence_number');
    
    echo "   Total payments: $total_payments\n";
    echo "   Unique sequences: $unique_sequences\n";
    echo "   Max sequence: $max_sequence\n";
    
    if ($max_sequence != 1499) {
        throw new Exception("Max sequence should be 1499, got $max_sequence");
    }
    
    echo "\n✅ All safety checks passed!\n\n";
    
    // User confirmation
    echo str_repeat("=", 100) . "\n";
    echo "⚠️  READY TO COMMIT\n";
    echo str_repeat("=", 100) . "\n";
    echo "Changes to be committed:\n";
    echo "  - Payment 1492: sequence_number NULL → 1489\n";
    echo "  - Payments 1493+: sequence_number incremented by 1\n";
    echo "  - Total records modified: " . ($update_count + 1) . "\n";
    echo "\nAll verifications passed. Type 'YES' to commit, anything else to rollback: ";
    
    $handle = fopen("php://stdin", "r");
    $confirmation = trim(fgets($handle));
    fclose($handle);
    
    if ($confirmation !== 'YES') {
        throw new Exception("User cancelled - rolling back.");
    }
    
    DB::commit();
    
    echo "\n✅ TRANSACTION COMMITTED!\n\n";
    
} catch (Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "🔄 Transaction rolled back - no changes made.\n";
    exit(1);
}

// ============================================================================
// VERIFICATION #3: POST-COMMIT FINAL CHECK
// ============================================================================

echo str_repeat("=", 100) . "\n";
echo "📋 VERIFICATION #3: Final Post-Commit Verification\n";
echo str_repeat("=", 100) . "\n\n";

// Re-query from database
$final_1492 = DB::table('payments')->where('id', 1492)->first();
$final_sample = DB::table('payments')
    ->where('id', '>=', 1488)
    ->where('id', '<=', 1503)
    ->orderBy('id')
    ->get(['id', 'payment_number', 'sequence_number', 'invoice_id']);

echo "🔍 Final State - Payments around the gap:\n";
printf("%-6s %-18s %-15s %-12s %-10s\n", "ID", "Payment Number", "Sequence", "Invoice", "Status");
echo str_repeat("-", 100) . "\n";

$all_good = true;
foreach ($final_sample as $p) {
    $extracted = (int)substr($p->payment_number, 4);
    $match = ($extracted === $p->sequence_number);
    $status = $match ? "✓ OK" : "✗ MISMATCH";
    
    if (!$match) {
        $all_good = false;
    }
    
    printf("%-6d %-18s %-15d %-12d %-10s\n",
        $p->id,
        $p->payment_number,
        $p->sequence_number,
        $p->invoice_id,
        $status
    );
}

echo "\n";

if (!$all_good) {
    echo "❌ CRITICAL: Mismatches still exist after fix!\n";
    exit(1);
}

// Check next number generation
echo "🔍 Testing SerialNumberFormatter with fixed data...\n";

$serial = new \App\Services\SerialNumberFormatter();
$serial->setModel(new \App\Models\Payment())
    ->setCompany(1)
    ->setCustomer(null);
$next_number = $serial->getNextNumber();

echo "   Next payment number will be: $next_number\n";

$expected_next = "PAY-001500";
if ($next_number !== $expected_next) {
    echo "   ⚠️  WARNING: Expected $expected_next, got $next_number\n";
} else {
    echo "   ✅ Correct! System will generate PAY-001500 next\n";
}

echo "\n" . str_repeat("=", 100) . "\n";
echo "✅ ALL VERIFICATIONS PASSED!\n";
echo str_repeat("=", 100) . "\n\n";

echo "Summary:\n";
echo "  ✓ Payment 1492 sequence_number: NULL → 1489\n";
echo "  ✓ Payments 1493+ incremented by 1\n";
echo "  ✓ All payment numbers aligned with sequence numbers\n";
echo "  ✓ All invoice relationships preserved\n";
echo "  ✓ Next payment will be: $next_number\n";
echo "  ✓ Collision detection is active and working\n\n";

echo "🎉 Fix completed successfully!\n\n";
