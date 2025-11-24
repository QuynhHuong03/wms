<?php
/**
 * Recalculate all bin capacities based on actual inventory
 * Run this script to sync bin capacity percentages with real product volumes
 */

require_once(__DIR__ . '/../model/mLocation.php');

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get warehouse ID from command line or session
$warehouseId = $argv[1] ?? $_SESSION['login']['warehouse_id'] ?? null;

echo "===========================================\n";
echo "  🔄 Recalculate Bin Capacities\n";
echo "===========================================\n\n";

if (!$warehouseId) {
    echo "❌ Error: No warehouse ID provided\n\n";
    echo "Usage:\n";
    echo "  php recalculate_bin_capacities.php <warehouse_id>\n\n";
    echo "Example:\n";
    echo "  php recalculate_bin_capacities.php KHO_TONG_01\n\n";
    
    // Try to list available warehouses
    try {
        $mLocation = new MLocation();
        $locations = $mLocation->getAllLocations();
        
        if (!empty($locations)) {
            $warehouses = [];
            foreach ($locations as $loc) {
                $whInfo = $loc['warehouse'] ?? [];
                $whId = $whInfo['id'] ?? 'unknown';
                if (!in_array($whId, $warehouses)) {
                    $warehouses[] = $whId;
                }
            }
            
            if (!empty($warehouses)) {
                echo "Available warehouses:\n";
                foreach ($warehouses as $wh) {
                    echo "  - {$wh}\n";
                }
            }
        }
    } catch (Exception $e) {
        // Ignore errors
    }
    
    exit(1);
}

echo "🏢 Warehouse: {$warehouseId}\n\n";

try {
    $mLocation = new MLocation();
    
    echo "⏳ Calculating bin capacities...\n\n";
    
    $result = $mLocation->recalculateAllBinCapacities($warehouseId);
    
    if (!$result['success']) {
        echo "❌ Error: " . ($result['error'] ?? 'Unknown error') . "\n";
        if (isset($result['trace'])) {
            echo "\nStack trace:\n{$result['trace']}\n";
        }
        exit(1);
    }
    
    $stats = $result['stats'] ?? [];
    
    echo "✅ Calculation complete!\n\n";
    echo "📊 Statistics:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  Total bins:            " . ($stats['total_bins'] ?? 0) . "\n";
    echo "  Bins with inventory:   " . ($stats['bins_with_inventory'] ?? 0) . "\n";
    echo "  Bins updated:          " . ($stats['updated_bins'] ?? 0) . "\n";
    echo "  DB matched:            " . ($result['matched'] ?? 0) . "\n";
    echo "  DB modified:           " . ($result['modified'] ?? 0) . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    if (!empty($stats['errors'])) {
        echo "⚠️ Errors encountered:\n";
        foreach ($stats['errors'] as $error) {
            echo "  - {$error}\n";
        }
        echo "\n";
    }
    
    echo "💡 Next steps:\n";
    echo "  1. View updated capacities in the warehouse locations page\n";
    echo "  2. Run ML training with updated data: php collect_data.php {$warehouseId}\n";
    echo "  3. Train model: python train_model.py\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
