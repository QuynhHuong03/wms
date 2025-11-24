<?php
// Sync bin quantities from inventory to locations
include_once(__DIR__ . '/../model/mInventory.php');
include_once(__DIR__ . '/../model/mLocation.php');

class CSync {
    
    // Sync all bin quantities from inventory to locations collection
    public function syncBinQuantitiesFromInventory($warehouseId) {
        $mInventory = new MInventory();
        $mLocation = new MLocation();
        
        // Get location structure
        $loc = $mLocation->getLocationByWarehouseId($warehouseId);
        if (!$loc || empty($loc['zones'])) {
            return ['ok' => false, 'error' => 'Warehouse location not found'];
        }
        
        $updated = 0;
        $zones = $loc['zones'] ?? [];
        
        // Loop through all bins and calculate quantity from inventory
        foreach ($zones as $zIdx => $zone) {
            $zoneId = $zone['_id'] ?? $zone['zone_id'] ?? '';
            if (!$zoneId) continue;
            
            foreach ($zone['racks'] ?? [] as $rIdx => $rack) {
                $rackId = $rack['rack_id'] ?? '';
                if (!$rackId) continue;
                
                foreach ($rack['bins'] ?? [] as $bIdx => $bin) {
                    $binId = $bin['bin_id'] ?? $bin['id'] ?? '';
                    if (!$binId) continue;
                    
                    // Sum all inventory entries for this bin
                    $totalQty = $mInventory->sumQuantityByBin($warehouseId, $zoneId, $rackId, $binId);
                    
                    // Update bin quantity in locations
                    $zones[$zIdx]['racks'][$rIdx]['bins'][$bIdx]['quantity'] = $totalQty;
                    $updated++;
                }
            }
        }
        
        // Save updated zones
        if ($updated > 0) {
            $p = new clsKetNoi();
            $con = $p->moKetNoi();
            if ($con) {
                try {
                    $col = $con->selectCollection('locations');
                    $result = $col->updateOne(
                        ['warehouse_id' => $warehouseId],
                        ['$set' => ['zones' => $zones]]
                    );
                    $p->dongKetNoi($con);
                    return ['ok' => true, 'updated' => $updated];
                } catch (Exception $e) {
                    $p->dongKetNoi($con);
                    return ['ok' => false, 'error' => $e->getMessage()];
                }
            }
        }
        
        return ['ok' => true, 'updated' => 0];
    }
}
?>
