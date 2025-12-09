<?php

/**
 * Fix Hash Collisions Script
 * 
 * This script finds any records with duplicate or non-decodable hashes
 * and regenerates them with unique random hashes.
 * 
 * Run after fix_regenerate_all_hashes.php if there are collision errors.
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  Fix Hash Collisions                                          ║\n";
echo "║  Regenerate problematic hashes with unique random values      ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$tables = [
    'invoices' => 'App\Models\Invoice',
    'payments' => 'App\Models\Payment', 
    'estimates' => 'App\Models\Estimate',
    'appointments' => 'App\Models\Appointment',
];

$totalFixed = 0;

foreach ($tables as $table => $model) {
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "Checking $table...\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    // Check if table exists and has unique_hash column
    if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'unique_hash')) {
        echo "  ⊘ Table $table doesn't exist or has no unique_hash column, skipping.\n\n";
        continue;
    }
    
    // Find records with non-decodable hashes
    $records = DB::table($table)->whereNotNull('unique_hash')->get(['id', 'unique_hash']);
    $needsFix = [];
    
    foreach ($records as $record) {
        try {
            $decoded = Hashids::connection($model)->decode($record->unique_hash);
            if (empty($decoded) || $decoded[0] != $record->id) {
                $needsFix[] = $record->id;
            }
        } catch (Exception $e) {
            $needsFix[] = $record->id;
        }
    }
    
    if (empty($needsFix)) {
        echo "  ✓ All hashes in $table are valid.\n\n";
        continue;
    }
    
    echo "  Found " . count($needsFix) . " records needing hash fix.\n";
    echo "  Fixing...\n";
    
    $fixed = 0;
    foreach ($needsFix as $id) {
        $attempts = 0;
        $success = false;
        while ($attempts < 100 && !$success) {
            $hash = bin2hex(random_bytes(15)); // 30 char hex string, unique
            try {
                DB::table($table)->where('id', $id)->update(['unique_hash' => $hash]);
                echo "    ✓ $table ID $id: $hash\n";
                $success = true;
                $fixed++;
            } catch (Exception $e) {
                $attempts++;
            }
        }
        if (!$success) {
            echo "    ✗ $table ID $id: Failed after 100 attempts\n";
        }
    }
    
    echo "  Fixed: $fixed / " . count($needsFix) . "\n\n";
    $totalFixed += $fixed;
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "COMPLETE: Fixed $totalFixed total records.\n";
echo "═══════════════════════════════════════════════════════════════\n\n";
