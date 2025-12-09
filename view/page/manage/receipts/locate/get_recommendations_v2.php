<?php
/**
 * ========================================
 * BIN RECOMMENDATION ALGORITHM v2.0
 * ========================================
 * Thuật toán gợi ý vị trí xếp hàng tối ưu
 * Tuân thủ đầy đủ tài liệu khoa học
 * 
 * INPUT:
 * - product_id, product_size, category, expected_turnover, qty_to_store
 * - bin info: bin_id, zone_id, zone_position, zone_type, capacity, 
 *   current_volume, available_volume, current_qty, stored_product_id, stored_category
 * 
 * OUTPUT:
 * - Top-k bins phân theo 4 nhóm:
 *   1. Same Product
 *   2. Same Category  
 *   3. High Available Volume
 *   4. Optimal Fill Efficiency (80-95%)
 * 
 * SCORING CRITERIA:
 * 1. Zone Bonus = (10 - zone_position) × 0.01
 * 2. Fill Efficiency Score (optimal 80-95%)
 * 3. Minimize Split Penalty
 * 4. Quality Score = Fill Efficiency + Zone Bonus
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

include_once(__DIR__ . "/../../../../../controller/cProduct.php");
include_once(__DIR__ . "/../../../../../controller/clocation.php");
include_once(__DIR__ . "/../../../../../model/mLocation.php");

try {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = $input['product_id'] ?? '';
    $quantity = (int)($input['quantity'] ?? 1);
    $inputUnit = trim($input['unit'] ?? '');
    
    // Get warehouse_id
    $warehouseId = $input['warehouse_id'] ?? '';
    if (!$warehouseId && isset($_SESSION['login']['warehouse_id'])) {
        $warehouseId = $_SESSION['login']['warehouse_id'];
    }
    
    if (!$warehouseId && isset($input['receipt_id'])) {
        include_once(__DIR__ . "/../../../../../controller/cReceipt.php");
        $cReceipt = new CReceipt();
        $receipt = $cReceipt->getReceiptById($input['receipt_id']);
        if ($receipt && isset($receipt['warehouse_id'])) {
            $warehouseId = $receipt['warehouse_id'];
        }
    }
    
    if (!$productId) {
        echo json_encode(['success' => false, 'error' => 'Missing product_id']);
        exit;
    }
    
    if (!$warehouseId) {
        echo json_encode(['success' => false, 'error' => 'Missing warehouse_id']);
        exit;
    }
    
    // ========================================
    // STEP 1: LOAD PRODUCT INFO
    // ========================================
    $cProduct = new CProduct();
    $product = $cProduct->getProductById($productId);
    
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }
    
    // Get product category
    $productCategory = '';
    $productCategoryId = '';
    if (isset($product['category'])) {
        if (is_array($product['category'])) {
            $productCategoryId = $product['category']['id'] ?? '';
            $productCategory = $product['category']['name'] ?? '';
        } else {
            $productCategory = (string)$product['category'];
        }
    }
    
    // Get product dimensions based on unit
    $baseUnit = $product['baseUnit'] ?? 'cái';
    $convUnits = $product['conversionUnits'] ?? [];
    $useDimensions = null;
    $unitLabel = $baseUnit;
    
    if ($inputUnit && strcasecmp($inputUnit, $baseUnit) !== 0) {
        foreach ($convUnits as $cu) {
            if (strcasecmp(trim($cu['unit'] ?? ''), $inputUnit) === 0) {
                if (isset($cu['dimensions']) && is_array($cu['dimensions'])) {
                    $useDimensions = $cu['dimensions'];
                    $unitLabel = $cu['unit'];
                }
                break;
            }
        }
    }
    
    if (!$useDimensions) {
        $useDimensions = $product['package_dimensions'] ?? $product['dimensions'] ?? [];
        $unitLabel = $baseUnit;
    }
    
    if ($useDimensions) {
        $product['package_dimensions'] = $useDimensions;
        $product['dimensions'] = $useDimensions;
    }
    
    $pWidth = (float)($useDimensions['width'] ?? 0);
    $pDepth = (float)($useDimensions['depth'] ?? 0);
    $pHeight = (float)($useDimensions['height'] ?? 0);
    $pVolume = $pWidth * $pDepth * $pHeight;
    
    if ($pVolume <= 0) {
        echo json_encode([
            'success' => false, 
            'error' => 'Product dimensions not set',
            'debug' => ['width' => $pWidth, 'depth' => $pDepth, 'height' => $pHeight]
        ]);
        exit;
    }
    
    // Get stacking info
    $stackable = (bool)($product['stackable'] ?? true);
    $maxStackHeight = (int)($product['max_stack_height'] ?? 1);
    if ($maxStackHeight < 1) $maxStackHeight = 1;
    
    // Get turnover (optional - for future optimization)
    $expectedTurnover = $product['expected_turnover'] ?? 'medium';
    
    // ========================================
    // STEP 2: LOAD WAREHOUSE LOCATION
    // ========================================
    $mLocation = new MLocation();
    $location = $mLocation->getLocationByWarehouseId($warehouseId);
    
    if (!$location || empty($location['zones'])) {
        echo json_encode([
            'success' => false, 
            'error' => 'Warehouse has no zones configured'
        ]);
        exit;
    }
    
    // Count products by category in each zone
    $zoneCategoryCount = [];
    $zones = $location['zones'] ?? [];
    
    foreach ($zones as $zone) {
        $zoneId = $zone['zone_id'] ?? '';
        $zoneCategoryCount[$zoneId] = 0;
        
        if (empty($zone['racks'])) continue;
        
        foreach ($zone['racks'] as $rack) {
            if (empty($rack['bins'])) continue;
            
            foreach ($rack['bins'] as $bin) {
                $binProduct = $bin['product'] ?? [];
                $binProductId = is_array($binProduct) ? ($binProduct['id'] ?? '') : '';
                
                if (!$binProductId) continue;
                
                $binProdCategoryId = '';
                $binProdCategory = '';
                
                if (isset($binProduct['category'])) {
                    if (is_array($binProduct['category'])) {
                        $binProdCategoryId = $binProduct['category']['id'] ?? '';
                        $binProdCategory = $binProduct['category']['name'] ?? '';
                    } else {
                        $binProdCategory = (string)$binProduct['category'];
                    }
                }
                
                if ($productCategoryId && $binProdCategoryId && $productCategoryId === $binProdCategoryId) {
                    $zoneCategoryCount[$zoneId]++;
                } elseif ($productCategory && $binProdCategory && strcasecmp($productCategory, $binProdCategory) === 0) {
                    $zoneCategoryCount[$zoneId]++;
                }
            }
        }
    }
    
    // ========================================
    // STEP 3: EXTRACT & FILTER BINS
    // ========================================
    $bins = [];
    
    foreach ($zones as $zoneIndex => $zone) {
        $zoneId = $zone['zone_id'] ?? '';
        
        // Extract zone position from ID (Z1=1, Z2=2, ...)
        $zonePosition = 1;
        if (preg_match('/Z(\d+)/i', $zoneId, $matches)) {
            $zonePosition = (int)$matches[1];
        } else {
            $zonePosition = $zoneIndex + 1;
        }
        
        if (empty($zone['racks'])) continue;
        
        foreach ($zone['racks'] as $rackIndex => $rack) {
            $rackId = $rack['rack_id'] ?? '';
            $rackPosition = $rackIndex + 1;
            
            if (empty($rack['bins'])) continue;
            
            foreach ($rack['bins'] as $binIndex => $bin) {
                $binId = $bin['bin_id'] ?? '';
                $binCode = $bin['code'] ?? "{$zoneId}-{$rackId}-{$binId}";
                
                // Get bin capacity (% sử dụng hiện tại)
                $currentCapacityPercent = (float)($bin['current_capacity'] ?? 0);
                
                // Get bin's current product
                $binProduct = $bin['product'] ?? [];
                $binProductId = is_array($binProduct) ? ($binProduct['id'] ?? '') : '';
                $currentQty = (int)($bin['quantity'] ?? 0);
                
                // Reset capacity nếu bin trống
                if ($currentQty <= 0) {
                    $currentCapacityPercent = 0;
                }
                
                // Get bin dimensions
                $dimensions = $bin['dimensions'] ?? [];
                $binWidth = (float)($dimensions['width'] ?? 50);
                $binDepth = (float)($dimensions['depth'] ?? 40);
                $binHeight = (float)($dimensions['height'] ?? 30);
                $binVolume = $binWidth * $binDepth * $binHeight;
                
                // ========================================
                // FILTER 1: Physical Fit Check
                // ========================================
                $pSorted = [$pWidth, $pDepth, $pHeight];
                $bSorted = [$binWidth, $binDepth, $binHeight];
                sort($pSorted);
                sort($bSorted);
                
                if ($pSorted[0] > $bSorted[0] || $pSorted[1] > $bSorted[1] || $pSorted[2] > $bSorted[2]) {
                    continue; // Product too large
                }
                
                // ========================================
                // FILTER 2: Category Compatibility
                // ========================================
                $hasSameProduct = false;
                $hasSameCategory = false;
                $binProductCategory = '';
                $binProductCategoryId = '';
                
                if ($binProductId) {
                    if ($binProductId === $productId) {
                        $hasSameProduct = true;
                    } else {
                        // Get category
                        if (is_array($binProduct) && isset($binProduct['category'])) {
                            if (is_array($binProduct['category'])) {
                                $binProductCategoryId = $binProduct['category']['id'] ?? '';
                                $binProductCategory = $binProduct['category']['name'] ?? '';
                            } else {
                                $binProductCategory = (string)$binProduct['category'];
                            }
                        }
                        
                        // If not in bin data, fetch from DB
                        if (!$binProductCategory && $binProductId) {
                            try {
                                $binProductInfo = $cProduct->getProductById($binProductId);
                                if ($binProductInfo && isset($binProductInfo['category'])) {
                                    if (is_array($binProductInfo['category'])) {
                                        $binProductCategoryId = $binProductInfo['category']['id'] ?? '';
                                        $binProductCategory = $binProductInfo['category']['name'] ?? '';
                                    } else {
                                        $binProductCategory = (string)$binProductInfo['category'];
                                    }
                                }
                            } catch (Exception $e) {}
                        }
                        
                        // Check category match
                        if ($productCategoryId && $binProductCategoryId && $productCategoryId === $binProductCategoryId) {
                            $hasSameCategory = true;
                        } elseif ($productCategory && $binProductCategory && strcasecmp($productCategory, $binProductCategory) === 0) {
                            $hasSameCategory = true;
                        }
                    }
                    
                    // FILTER: Skip if different category (không cho trộn lẫn)
                    if (!$hasSameProduct && !$hasSameCategory) {
                        continue;
                    }
                }
                
                // ========================================
                // FILTER 3: Capacity Check
                // ========================================
                if ($currentCapacityPercent >= 100) {
                    continue; // Bin full
                }
                
                // ========================================
                // STEP 4: CALCULATE ITEMS CAN FIT
                // ========================================
                
                // Try all orientations
                $orientations = [
                    ['w' => $pWidth, 'd' => $pDepth, 'h' => $pHeight],
                    ['w' => $pWidth, 'd' => $pHeight, 'h' => $pDepth],
                    ['w' => $pDepth, 'd' => $pWidth, 'h' => $pHeight],
                    ['w' => $pDepth, 'd' => $pHeight, 'h' => $pWidth],
                    ['w' => $pHeight, 'd' => $pWidth, 'h' => $pDepth],
                    ['w' => $pHeight, 'd' => $pDepth, 'h' => $pWidth]
                ];
                
                $bestFit = 0;
                $bestOrientation = null;
                $fitMethod = 'horizontal';
                
                foreach ($orientations as $orient) {
                    $ow = $orient['w'];
                    $od = $orient['d'];
                    $oh = $orient['h'];
                    
                    if ($ow > $binWidth || $od > $binDepth || $oh > $binHeight) {
                        continue;
                    }
                    
                    // Base layer capacity
                    $itemsPerRow = floor($binWidth / $ow);
                    $itemsPerCol = floor($binDepth / $od);
                    $baseLayerCapacity = $itemsPerRow * $itemsPerCol;
                    
                    if ($baseLayerCapacity == 0) continue;
                    
                    // Stack capacity
                    $totalCapacity = $baseLayerCapacity;
                    
                    if ($stackable && $maxStackHeight > 1) {
                        $maxLayers = min($maxStackHeight, floor($binHeight / $oh));
                        $totalCapacity = $baseLayerCapacity * $maxLayers;
                        $fitMethod = 'stacked';
                    }
                    
                    if ($totalCapacity > $bestFit) {
                        $bestFit = $totalCapacity;
                        $bestOrientation = $orient;
                    }
                }
                
                if ($bestFit == 0) {
                    continue; // Cannot fit
                }
                
                // Calculate remaining capacity by volume
                $currentVolumeUsed = ($currentCapacityPercent / 100) * $binVolume;
                $availableVolume = $binVolume - $currentVolumeUsed;
                
                $volumePerItem = $pVolume;
                $maxByVolume = floor($availableVolume / $volumePerItem);
                
                // Items can fit
                $itemsCanFit = min($quantity, $bestFit - $currentQty, $maxByVolume);
                
                if ($itemsCanFit <= 0) {
                    continue;
                }
                
                // ========================================
                // STEP 5: CALCULATE FILL RATE AFTER
                // ========================================
                $volumeAdded = $itemsCanFit * $pVolume;
                $newVolumeUsed = $currentVolumeUsed + $volumeAdded;
                $fillRateAfter = ($newVolumeUsed / $binVolume) * 100;
                $fillRateAfter = min(100, $fillRateAfter);
                
                // ========================================
                // STEP 6: CALCULATE ZONE BONUS
                // ========================================
                // zone_bonus = (10 - zone_position) × 0.01
                $zoneBonus = max(0, (10 - $zonePosition) * 0.01);
                
                // ========================================
                // STEP 7: CALCULATE FILL EFFICIENCY SCORE
                // ========================================
                // Optimal range: 80-95%
                // Max score: 1.0 when in range
                // Decrease when outside range
                
                $fillEfficiencyScore = 0;
                
                if ($fillRateAfter >= 80 && $fillRateAfter <= 95) {
                    // Perfect range
                    $fillEfficiencyScore = 1.0;
                } elseif ($fillRateAfter < 80) {
                    // Below optimal: score decreases linearly from 0.8 to 0
                    // At 0%: score = 0
                    // At 79%: score = 0.79
                    $fillEfficiencyScore = $fillRateAfter / 80 * 0.8;
                } else {
                    // Above 95%: penalty for being too full
                    // At 96%: score = 0.95
                    // At 100%: score = 0.5 (heavy penalty)
                    $excess = $fillRateAfter - 95;
                    $fillEfficiencyScore = 1.0 - ($excess / 5) * 0.5;
                    $fillEfficiencyScore = max(0.5, $fillEfficiencyScore);
                }
                
                // ========================================
                // STEP 8: CALCULATE SPLIT PENALTY
                // ========================================
                // If this bin cannot hold all qty, apply penalty
                $splitPenalty = 0;
                if ($itemsCanFit < $quantity) {
                    // Penalty proportional to how much we need to split
                    $splitRatio = $itemsCanFit / $quantity;
                    $splitPenalty = (1 - $splitRatio) * 0.1; // Max 10% penalty
                }
                
                // ========================================
                // STEP 9: CALCULATE QUALITY SCORE
                // ========================================
                // quality_score = fill_efficiency_score + zone_bonus - split_penalty
                $qualityScore = $fillEfficiencyScore + $zoneBonus - $splitPenalty;
                $qualityScore = max(0, min(1.2, $qualityScore)); // Cap at 1.2
                
                // ========================================
                // STEP 10: STORE BIN DATA
                // ========================================
                $binFeatures = [
                    'bin_id' => "{$zoneId}/{$rackId}/{$binId}",
                    'bin_code' => $binCode,
                    'bin_volume' => $binVolume,
                    'bin_width' => $binWidth,
                    'bin_depth' => $binDepth,
                    'bin_height' => $binHeight,
                    'current_capacity' => $currentCapacityPercent,
                    'available_volume' => $availableVolume,
                    'fill_rate_after' => $fillRateAfter,
                    'fill_efficiency_score' => $fillEfficiencyScore,
                    'zone_bonus' => $zoneBonus,
                    'split_penalty' => $splitPenalty,
                    'quality_score' => $qualityScore,
                    'items_can_fit' => (int)$itemsCanFit,
                    'fit_method' => $fitMethod,
                    'zone_position' => $zonePosition,
                    'zone_id' => $zoneId,
                    'rack_id' => $rackId,
                    'bin_id_raw' => $binId,
                    'has_same_product' => $hasSameProduct ? 1 : 0,
                    'has_same_category' => $hasSameCategory ? 1 : 0,
                    'current_qty' => $currentQty,
                    'bin_product_category' => $binProductCategory,
                    'zone_category_count' => $zoneCategoryCount[$zoneId] ?? 0
                ];
                
                $bins[] = $binFeatures;
            }
        }
    }
    
    if (empty($bins)) {
        echo json_encode([
            'success' => true,
            'same_product_bins' => [],
            'same_category_bins' => [],
            'high_volume_bins' => [],
            'optimal_fill_bins' => [],
            'total_evaluated' => 0,
            'message' => 'Không có bin phù hợp'
        ]);
        exit;
    }
    
    // ========================================
    // STEP 11: CATEGORIZE BINS
    // ========================================
    $sameProductBins = [];
    $sameCategoryBins = [];
    $highVolumeBins = [];
    $optimalFillBins = [];
    
    foreach ($bins as $bin) {
        $hasSameProduct = ($bin['has_same_product'] ?? 0) == 1;
        $hasSameCategory = ($bin['has_same_category'] ?? 0) == 1;
        $fillRateAfter = $bin['fill_rate_after'] ?? 0;
        $availableVol = $bin['available_volume'] ?? 0;
        
        // Category 1: Same Product (highest priority)
        if ($hasSameProduct) {
            $sameProductBins[] = $bin;
        }
        // Category 2: Same Category
        elseif ($hasSameCategory) {
            $sameCategoryBins[] = $bin;
        }
        
        // Category 3: High Available Volume (empty or lots of space)
        // Threshold: >60% volume available
        $availablePercent = ($availableVol / $bin['bin_volume']) * 100;
        if ($availablePercent > 60) {
            $highVolumeBins[] = $bin;
        }
        
        // Category 4: Optimal Fill (80-95% after allocation)
        if ($fillRateAfter >= 80 && $fillRateAfter <= 95) {
            $optimalFillBins[] = $bin;
        }
    }
    
    // ========================================
    // STEP 12: RANK & SELECT TOP-K
    // ========================================
    $enrichBin = function($bin) {
        return [
            'bin_id' => $bin['bin_id'],
            'bin_code' => $bin['bin_code'],
            'quality_score' => $bin['quality_score'],
            'quality_percentage' => round($bin['quality_score'] * 100, 1),
            'current_utilization' => $bin['current_capacity'],
            'utilization_after' => $bin['fill_rate_after'],
            'fill_efficiency_score' => round($bin['fill_efficiency_score'], 3),
            'zone_bonus' => round($bin['zone_bonus'], 3),
            'split_penalty' => round($bin['split_penalty'], 3),
            'fit_method' => $bin['fit_method'],
            'items_can_fit' => $bin['items_can_fit'],
            'has_same_product' => ($bin['has_same_product'] ?? 0) == 1,
            'has_same_category' => ($bin['has_same_category'] ?? 0) == 1,
            'current_qty' => $bin['current_qty'],
            'zone_id' => $bin['zone_id'],
            'rack_id' => $bin['rack_id'],
            'bin_id_raw' => $bin['bin_id_raw'],
            'zone_category_count' => $bin['zone_category_count']
        ];
    };
    
    // Sort and select top 5 for each category
    
    // 1. Same Product: sort by quality_score DESC
    usort($sameProductBins, function($a, $b) {
        return $b['quality_score'] <=> $a['quality_score'];
    });
    $sameProductResults = array_slice($sameProductBins, 0, 5);
    $sameProductResults = array_map(function($bin, $idx) use ($enrichBin) {
        $enriched = $enrichBin($bin);
        $enriched['rank'] = $idx + 1;
        $enriched['quality_label'] = '⭐ Cùng sản phẩm';
        $enriched['quality_color'] = '#7c3aed';
        $enriched['category'] = 'same_product';
        return $enriched;
    }, $sameProductResults, array_keys($sameProductResults));
    
    // 2. Same Category: sort by quality_score DESC
    usort($sameCategoryBins, function($a, $b) {
        return $b['quality_score'] <=> $a['quality_score'];
    });
    $sameCategoryResults = array_slice($sameCategoryBins, 0, 5);
    $sameCategoryResults = array_map(function($bin, $idx) use ($enrichBin) {
        $enriched = $enrichBin($bin);
        $enriched['rank'] = $idx + 1;
        $enriched['quality_label'] = '🏷️ Cùng loại';
        $enriched['quality_color'] = '#f59e0b';
        $enriched['category'] = 'same_category';
        return $enriched;
    }, $sameCategoryResults, array_keys($sameCategoryResults));
    
    // 3. High Volume: sort by available_volume DESC
    usort($highVolumeBins, function($a, $b) {
        $volA = $a['available_volume'] ?? 0;
        $volB = $b['available_volume'] ?? 0;
        return $volB <=> $volA;
    });
    $highVolumeResults = array_slice($highVolumeBins, 0, 5);
    $highVolumeResults = array_map(function($bin, $idx) use ($enrichBin) {
        $enriched = $enrichBin($bin);
        $enriched['rank'] = $idx + 1;
        $enriched['quality_label'] = '📦 Thể tích lớn';
        $enriched['quality_color'] = '#059669';
        $enriched['category'] = 'high_volume';
        return $enriched;
    }, $highVolumeResults, array_keys($highVolumeResults));
    
    // 4. Optimal Fill: sort by how close to 87.5% (middle of 80-95%)
    usort($optimalFillBins, function($a, $b) {
        $distA = abs(87.5 - ($a['fill_rate_after'] ?? 0));
        $distB = abs(87.5 - ($b['fill_rate_after'] ?? 0));
        return $distA <=> $distB;
    });
    $optimalFillResults = array_slice($optimalFillBins, 0, 5);
    $optimalFillResults = array_map(function($bin, $idx) use ($enrichBin) {
        $enriched = $enrichBin($bin);
        $enriched['rank'] = $idx + 1;
        $enriched['quality_label'] = '✅ Lấp đầy tối ưu';
        $enriched['quality_color'] = '#0ea5e9';
        $enriched['category'] = 'optimal_fill';
        return $enriched;
    }, $optimalFillResults, array_keys($optimalFillResults));
    
    // ========================================
    // STEP 13: RETURN RESULTS
    // ========================================
    $response = [
        'success' => true,
        'same_product_bins' => $sameProductResults,
        'same_category_bins' => $sameCategoryResults,
        'high_volume_bins' => $highVolumeResults,
        'optimal_fill_bins' => $optimalFillResults,
        'total_evaluated' => count($bins),
        'counts' => [
            'same_product' => count($sameProductResults),
            'same_category' => count($sameCategoryResults),
            'high_volume' => count($highVolumeResults),
            'optimal_fill' => count($optimalFillResults)
        ],
        'product_name' => $product['product_name'] ?? $product['name'] ?? 'N/A',
        'product_dimensions' => sprintf(
            "%.1f×%.1f×%.1f cm",
            $pWidth,
            $pDepth,
            $pHeight
        ),
        'algorithm_version' => '2.0'
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
