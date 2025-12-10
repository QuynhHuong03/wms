<?php
/**
 * Get rule-based bin placement recommendations
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to output
ob_start(); // Start output buffering to catch any stray output

if (session_status() === PHP_SESSION_NONE) session_start();

include_once(__DIR__ . "/../../../../../controller/cProduct.php");
include_once(__DIR__ . "/../../../../../controller/clocation.php");

try {
    ob_clean(); // Clean any output before sending JSON
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = $input['product_id'] ?? '';
    $quantity = (int)($input['quantity'] ?? 1);
    $inputUnit = trim($input['unit'] ?? ''); // Get unit from request
    
    // Get warehouse_id from input, session login, or receipt
    $warehouseId = $input['warehouse_id'] ?? '';
    if (!$warehouseId && isset($_SESSION['login']['warehouse_id'])) {
        $warehouseId = $_SESSION['login']['warehouse_id'];
    }
    
    // If still no warehouse_id, try to get from receipt
    if (!$warehouseId && isset($input['receipt_id'])) {
        include_once(__DIR__ . "/../../../../../controller/cReceipt.php");
        $cReceipt = new CReceipt();
        $receipt = $cReceipt->getReceiptById($input['receipt_id']);
        if ($receipt && isset($receipt['warehouse_id'])) {
            $warehouseId = $receipt['warehouse_id'];
        }
    }
    
    if (!$productId) {
        echo json_encode(['success' => false, 'error' => 'Missing product_id', 'debug' => ['input' => $input]]);
        exit;
    }
    
    if (!$warehouseId) {
        echo json_encode([
            'success' => false, 
            'error' => 'Missing warehouse_id', 
            'debug' => [
                'input_warehouse_id' => $input['warehouse_id'] ?? 'not set',
                'session_login' => isset($_SESSION['login']) ? 'exists' : 'missing',
                'session_warehouse_id' => $_SESSION['login']['warehouse_id'] ?? 'not set'
            ]
        ]);
        exit;
    }
    
    // Get product information (using rule-based algorithm)
    $cProduct = new CProduct();
    $product = $cProduct->getProductById($productId);
    
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }
    
    // Get product category for same-category bin matching
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
    error_log("Product category: ID=$productCategoryId, Name=$productCategory");
    
    // Determine which dimensions to use based on input unit
    // If user selects "Thùng", use thùng dimensions (400×500×150) to find bins that fit whole boxes
    // If user selects "Cái", use cái dimensions (40×50×15) to find bins that fit individual items
    $baseUnit = $product['baseUnit'] ?? 'cái';
    $convUnits = $product['conversionUnits'] ?? [];
    $useDimensions = null;
    $unitLabel = $baseUnit;
    
    // If inputUnit is provided and different from base unit, try to find conversion unit dimensions
    if ($inputUnit && strcasecmp($inputUnit, $baseUnit) !== 0) {
        foreach ($convUnits as $cu) {
            if (strcasecmp(trim($cu['unit'] ?? ''), $inputUnit) === 0) {
                if (isset($cu['dimensions']) && is_array($cu['dimensions'])) {
                    $useDimensions = $cu['dimensions'];
                    $unitLabel = $cu['unit'];
                    error_log("Using conversion unit dimensions for $inputUnit: " . json_encode($useDimensions));
                }
                break;
            }
        }
    }
    
    // Fallback to base product dimensions if no conversion unit dimensions found
    if (!$useDimensions) {
        $useDimensions = $product['package_dimensions'] ?? $product['dimensions'] ?? [];
        $unitLabel = $baseUnit;
        error_log("Using base unit dimensions: " . json_encode($useDimensions));
    }
    
    // Override product dimensions for feature extraction
    if ($useDimensions) {
        $product['package_dimensions'] = $useDimensions;
        $product['dimensions'] = $useDimensions;
    }
    
    // Get warehouse location structure (zones/racks/bins)
    include_once(__DIR__ . "/../../../../../model/mLocation.php");
    $mLocation = new MLocation();
    $location = $mLocation->getLocationByWarehouseId($warehouseId);
    
    if (!$location || empty($location['zones'])) {
        echo json_encode([
            'success' => false, 
            'error' => 'Warehouse has no zones configured',
            'debug' => [
                'warehouse_id' => $warehouseId,
                'location_found' => $location ? 'yes' : 'no',
                'zones_count' => isset($location['zones']) ? count($location['zones']) : 0
            ]
        ]);
        exit;
    }
    
    // Extract available bins
    $bins = [];
    $zones = $location['zones'] ?? [];
    
    // Count products by category in each zone for prioritization
    $zoneCategoryCount = [];
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
                
                // Get category of product in this bin
                $binProdCategory = '';
                $binProdCategoryId = '';
                
                if (isset($binProduct['category'])) {
                    if (is_array($binProduct['category'])) {
                        $binProdCategoryId = $binProduct['category']['id'] ?? '';
                        $binProdCategory = $binProduct['category']['name'] ?? '';
                    } else {
                        $binProdCategory = (string)$binProduct['category'];
                    }
                }
                
                // Check if this bin's product matches our product's category
                if ($productCategoryId && $binProdCategoryId && $productCategoryId === $binProdCategoryId) {
                    $zoneCategoryCount[$zoneId]++;
                } elseif ($productCategory && $binProdCategory && strcasecmp($productCategory, $binProdCategory) === 0) {
                    $zoneCategoryCount[$zoneId]++;
                }
            }
        }
    }
    
    foreach ($zones as $zoneIndex => $zone) {
        $zoneId = $zone['zone_id'] ?? '';
        
        // Extract zone position from zone ID (Z1=1, Z2=2, etc.)
        // This ensures correct position even if zones array is not in order
        $zonePosition = 1; // default
        if (preg_match('/Z(\d+)/i', $zoneId, $matches)) {
            $zonePosition = (int)$matches[1];
        } else {
            // Fallback to array index if zone_id doesn't match pattern
            $zonePosition = $zoneIndex + 1;
        }
        
        error_log("Processing Zone: {$zoneId} (position={$zonePosition})");
        
        if (empty($zone['racks'])) {
            error_log("  -> SKIPPED: No racks");
            continue;
        }
        
        foreach ($zone['racks'] as $rackIndex => $rack) {
            $rackId = $rack['rack_id'] ?? '';
            $rackPosition = $rackIndex + 1;
            
            if (empty($rack['bins'])) continue;
            
            foreach ($rack['bins'] as $binIndex => $bin) {
                $binId = $bin['bin_id'] ?? '';
                $binCode = $bin['code'] ?? "{$zoneId}-{$rackId}-{$binId}";
                $capacity = (float)($bin['current_capacity'] ?? 0);
                
                // Get bin's current product info
                $binProduct = $bin['product'] ?? [];
                $binProductId = is_array($binProduct) ? ($binProduct['id'] ?? '') : '';
                $currentQty = (int)($bin['quantity'] ?? 0);
                
                // ⭐ Fix: Nếu bin trống (qty = 0), reset capacity = 0
                if ($currentQty <= 0) {
                    $capacity = 0;
                }
                
                // Get category of product currently in bin (if any)
                $binProductCategory = '';
                $binProductCategoryId = '';
                if (is_array($binProduct) && $binProductId) {
                    // Try to get category from bin product data
                    if (isset($binProduct['category'])) {
                        if (is_array($binProduct['category'])) {
                            $binProductCategoryId = $binProduct['category']['id'] ?? '';
                            $binProductCategory = $binProduct['category']['name'] ?? '';
                        } else {
                            $binProductCategory = (string)$binProduct['category'];
                        }
                    }
                    
                    // If category not in bin data, fetch from product database
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
                        } catch (Exception $e) {
                            error_log("Error fetching bin product category: " . $e->getMessage());
                        }
                    }
                }
                
                // Extract bin dimensions from MongoDB structure
                $dimensions = $bin['dimensions'] ?? [];
                $binWidth = (float)($dimensions['width'] ?? 50);
                $binDepth = (float)($dimensions['depth'] ?? 40);
                $binHeight = (float)($dimensions['height'] ?? 30);
                $binVolume = $binWidth * $binDepth * $binHeight;
                
                // Check if product fits physically
                $pDims = $product['package_dimensions'] ?? $product['dimensions'] ?? [];
                $pWidth = (float)($pDims['width'] ?? 0);
                $pDepth = (float)($pDims['depth'] ?? 0);
                $pHeight = (float)($pDims['height'] ?? 0);
                $pVolume = $pWidth * $pDepth * $pHeight;
                
                // Check if product fits in any orientation (sort dimensions)
                $pSorted = [$pWidth, $pDepth, $pHeight];
                $bSorted = [$binWidth, $binDepth, $binHeight];
                sort($pSorted);
                sort($bSorted);
                
                // Product fits if smallest-to-largest dimensions can all fit
                if ($pSorted[0] > $bSorted[0] || $pSorted[1] > $bSorted[1] || $pSorted[2] > $bSorted[2]) {
                    error_log("Bin {$binCode}: SKIPPED - Product too large (P:{$pSorted[0]}×{$pSorted[1]}×{$pSorted[2]} vs B:{$bSorted[0]}×{$bSorted[1]}×{$bSorted[2]})");
                    continue; // Product doesn't fit in any orientation
                }
                
                // Get stacking info
                $stackable = (bool)($product['stackable'] ?? true);
                $maxStackHeight = (int)($product['max_stack_height'] ?? 1);
                if ($maxStackHeight < 1) $maxStackHeight = 1;
                
                // Calculate how product can fit based on stackable property and current content
                $fitMethod = '';
                $utilizationAfter = $capacity;
                $hasSameProduct = false;
                $hasSameCategory = false;
                
                // Check if bin already contains the same product
                if ($binProductId && $binProductId === $productId) {
                    $hasSameProduct = true;
                    error_log("Bin {$binCode}: Already contains same product (qty={$currentQty})");
                }
                // Check if bin contains product of same category
                elseif ($binProductId && $productCategory && $binProductCategory) {
                    // Match by category ID (more accurate) or by category name
                    if ($productCategoryId && $binProductCategoryId && $productCategoryId === $binProductCategoryId) {
                        $hasSameCategory = true;
                        error_log("Bin {$binCode}: Contains same category by ID (cat={$productCategory})");
                    } elseif (strcasecmp($productCategory, $binProductCategory) === 0) {
                        $hasSameCategory = true;
                        error_log("Bin {$binCode}: Contains same category by name (cat={$productCategory})");
                    }
                }
                
                // Try all possible orientations to find best fit
                $orientations = [
                    ['w' => $pWidth, 'd' => $pDepth, 'h' => $pHeight, 'name' => 'WxDxH'],
                    ['w' => $pWidth, 'd' => $pHeight, 'h' => $pDepth, 'name' => 'WxHxD'],
                    ['w' => $pDepth, 'd' => $pWidth, 'h' => $pHeight, 'name' => 'DxWxH'],
                    ['w' => $pDepth, 'd' => $pHeight, 'h' => $pWidth, 'name' => 'DxHxW'],
                    ['w' => $pHeight, 'd' => $pWidth, 'h' => $pDepth, 'name' => 'HxWxD'],
                    ['w' => $pHeight, 'd' => $pDepth, 'h' => $pWidth, 'name' => 'HxDxW']
                ];
                
                $bestFit = 0;
                $bestOrientation = null;
                
                foreach ($orientations as $orient) {
                    $ow = $orient['w'];
                    $od = $orient['d'];
                    $oh = $orient['h'];
                    
                    // Check if this orientation physically fits
                    if ($ow > $binWidth || $od > $binDepth || $oh > $binHeight) {
                        continue; // This orientation doesn't fit
                    }
                    
                    // Calculate how many items fit in base layer (horizontal placement)
                    $itemsPerRow = floor($binWidth / $ow);
                    $itemsPerCol = floor($binDepth / $od);
                    $baseLayerCapacity = $itemsPerRow * $itemsPerCol;
                    
                    if ($baseLayerCapacity == 0) continue;
                    
                    // Calculate maximum capacity considering stacking
                    $totalCapacity = $baseLayerCapacity;
                    
                    if ($stackable && $maxStackHeight > 1) {
                        // Can stack vertically
                        $maxLayers = min($maxStackHeight, floor($binHeight / $oh));
                        $totalCapacity = $baseLayerCapacity * $maxLayers;
                    }
                    
                    // Keep track of best orientation
                    if ($totalCapacity > $bestFit) {
                        $bestFit = $totalCapacity;
                        $bestOrientation = $orient;
                        $fitMethod = ($stackable && $maxLayers > 1) ? 'stacked' : 'horizontal';
                    }
                }
                
                if ($bestFit == 0) {
                    error_log("Bin {$binCode}: SKIPPED - No valid orientation found");
                    continue;
                }
                
                // Calculate remaining space based on volume
                $remainingCapacity = 100 - $capacity; // % còn lại
                $volumePerItem = ($pVolume > 0 && $binVolume > 0) ? ($pVolume / $binVolume) * 100 : 0;
                
                // Max items by volume capacity
                $maxByCapacity = 0;
                if ($volumePerItem > 0) {
                    $maxByCapacity = floor($remainingCapacity / $volumePerItem);
                }
                
                // If bin has same product and is stackable, can add more items by stacking
                $canAddByStacking = 0;
                if ($hasSameProduct && $stackable && $maxStackHeight > 1 && $bestOrientation) {
                    // Calculate current stack layers
                    $oh = $bestOrientation['h'];
                    if ($oh > 0) {
                        $currentLayers = ceil($currentQty / ($bestFit / min($maxStackHeight, floor($binHeight / $oh))));
                        $maxLayers = min($maxStackHeight, floor($binHeight / $oh));
                        
                        // Can add items until reaching max stack height
                        if ($currentLayers < $maxLayers) {
                            $itemsPerLayer = floor($bestFit / $maxLayers);
                            $canAddByStacking = ($maxLayers - $currentLayers) * $itemsPerLayer;
                            error_log("Bin {$binCode}: Can add by stacking - current layers: {$currentLayers}, max: {$maxLayers}, can add: {$canAddByStacking}");
                        }
                    }
                }
                
                // Calculate items that can fit
                // Priority: if same product and can stack, use stacking capacity
                // If same category, can add but with consideration
                // Otherwise use the minimum of physical fit and volume capacity
                $itemsCanFit = 0;
                
                if ($hasSameProduct) {
                    // Same product: can add by stacking or filling remaining space
                    $itemsCanFit = min($quantity, max($canAddByStacking, min($bestFit - $currentQty, $maxByCapacity)));
                } elseif ($hasSameCategory) {
                    // Same category but different product: can add to partially filled bin
                    // This allows grouping same-category products together
                    $itemsCanFit = min($quantity, min($bestFit - $currentQty, $maxByCapacity));
                } elseif ($currentQty == 0) {
                    // Empty bin: can use full capacity
                    $itemsCanFit = min($quantity, $bestFit, $maxByCapacity);
                } else {
                    // Different product AND different category already in bin: skip to avoid mixing
                    error_log("Bin {$binCode}: SKIPPED - Contains different category product");
                    continue;
                }
                
                // Skip if capacity reached
                if ($capacity >= 100) {
                    error_log("Bin {$binCode}: SKIPPED - Capacity full (100%)");
                    continue;
                }
                
                // Skip if cannot fit any items
                if ($itemsCanFit <= 0) {
                    error_log("Bin {$binCode}: SKIPPED - Cannot fit any items (bestFit={$bestFit}, maxByCapacity={$maxByCapacity}, canAddByStacking={$canAddByStacking})");
                    continue;
                }
                
                // Calculate volume usage after allocation
                $volumeUsed = ($pVolume * $itemsCanFit) / $binVolume * 100;
                $utilizationAfter = min(100, $capacity + $volumeUsed);
                
                error_log("Bin {$binCode}: ✅ ADDED - {$itemsCanFit} items fit, capacity: {$capacity}% -> {$utilizationAfter}%, method: {$fitMethod}, same_product: " . ($hasSameProduct ? 'yes' : 'no'));
                
                $binFeatures = [
                    'bin_id' => "{$zoneId}/{$rackId}/{$binId}",
                    'bin_code' => $bin['code'] ?? "{$zoneId}-{$rackId}-{$binId}",
                    'bin_volume' => $binVolume,
                    'bin_width' => $binWidth,
                    'bin_depth' => $binDepth,
                    'bin_height' => $binHeight,
                    'bin_capacity' => $capacity,
                    'bin_remaining_capacity' => 100 - $capacity,
                    'utilization_after' => $utilizationAfter,
                    'fit_method' => $fitMethod,
                    'items_can_fit' => (int)$itemsCanFit,
                    'rack_position' => $rackPosition,
                    'bin_position' => $binIndex + 1,
                    'zone_position' => $zonePosition,
                    'zone_id' => $zoneId,
                    'rack_id' => $rackId,
                    'bin_id_raw' => $binId,
                    'stackable' => $stackable ? 1 : 0,
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
            'fillable_bins' => [],
            'total_evaluated' => 0,
            'counts' => [
                'same_product' => 0,
                'same_category' => 0,
                'high_volume' => 0,
                'fillable' => 0
            ],
            'product_name' => $product['product_name'] ?? $product['name'] ?? 'N/A',
            'product_dimensions' => sprintf(
                "%.1f×%.1f×%.1f cm",
                $pWidth ?? 0,
                $pDepth ?? 0,
                $pHeight ?? 0
            ),
            'message' => 'Không có bin phù hợp (tất cả đã đầy hoặc không đủ kích thước)'
        ]);
        exit;
    }
    
    // Separate bins into 4 categories (added same-category)
    $sameProductBins = [];
    $sameCategoryBins = [];
    $emptyBins = [];
    $partialBins = [];
    
    foreach ($bins as $bin) {
        $hasSameProduct = ($bin['has_same_product'] ?? 0) == 1;
        $hasSameCategory = ($bin['has_same_category'] ?? 0) == 1;
        $currentCapacity = $bin['bin_capacity'] ?? 0;
        
        // Category 1: Bins with same product (for consolidation and stacking) - HIGHEST PRIORITY
        if ($hasSameProduct) {
            $sameProductBins[] = $bin;
        }
        // Category 2: Bins with same category product (for grouping similar items) - HIGH PRIORITY
        elseif ($hasSameCategory) {
            $sameCategoryBins[] = $bin;
        }
        // Category 3: Empty or low-utilization bins (high available volume)
        elseif ($currentCapacity < 30) {
            $emptyBins[] = $bin;
        }
        // Category 4: Bins that can be filled to near-full (30-85% current capacity)
        elseif ($currentCapacity >= 30 && $currentCapacity < 85) {
            $partialBins[] = $bin;
        }
    }
    
    // Helper function to enrich bin data
    $enrichBin = function($bin, $category) use ($pWidth, $pDepth, $pHeight) {
        $utilizationAfter = $bin['utilization_after'] ?? $bin['bin_capacity'];
        $currentUtil = $bin['bin_capacity'] ?? 0;
        
        // Calculate quality score based on category
        $quality_score = 0;
        if ($category === 'same_product') {
            // For same product: prefer bins that will be filled efficiently
            if ($utilizationAfter >= 85) {
                $quality_score = 1.0; // Will be nearly full
            } elseif ($utilizationAfter >= 70) {
                $quality_score = 0.9;
            } elseif ($utilizationAfter >= 50) {
                $quality_score = 0.8;
            } else {
                $quality_score = 0.7;
            }
        } elseif ($category === 'same_category') {
            // For same category: prefer bins with good utilization
            if ($utilizationAfter >= 75 && $utilizationAfter <= 95) {
                $quality_score = 0.95; // Excellent fill
            } elseif ($utilizationAfter >= 60) {
                $quality_score = 0.85;
            } elseif ($utilizationAfter >= 40) {
                $quality_score = 0.75;
            } else {
                $quality_score = 0.65;
            }
        } elseif ($category === 'high_volume') {
            // For empty bins: score by available volume (lower current = better)
            $quality_score = 1.0 - ($currentUtil / 100);
        } elseif ($category === 'fillable') {
            // For fillable bins: prefer those that will be 80-95% full after
            if ($utilizationAfter >= 80 && $utilizationAfter <= 95) {
                $quality_score = 1.0; // Perfect fill
            } elseif ($utilizationAfter >= 70 && $utilizationAfter < 80) {
                $quality_score = 0.85;
            } elseif ($utilizationAfter >= 95) {
                $quality_score = 0.9; // Good but very full
            } else {
                $quality_score = 0.7;
            }
        }
        
        // Add zone/rack accessibility bonus (small factor)
        $zoneBonus = max(0, (10 - ($bin['zone_position'] ?? 1)) * 0.01);
        $quality_score = min(1.0, $quality_score + $zoneBonus);
        
        return [
            'bin_id' => $bin['bin_id'],
            'bin_code' => $bin['bin_code'],
            'quality_score' => $quality_score,
            'quality_percentage' => round($quality_score * 100, 1),
            'current_utilization' => $currentUtil,
            'utilization_after' => $utilizationAfter,
            'fit_method' => $bin['fit_method'] ?? 'stacked',
            'items_can_fit' => $bin['items_can_fit'] ?? 0,
            'has_same_product' => ($bin['has_same_product'] ?? 0) == 1,
            'has_same_category' => ($bin['has_same_category'] ?? 0) == 1,
            'current_qty' => $bin['current_qty'] ?? 0,
            'zone_id' => $bin['zone_id'] ?? '',
            'rack_id' => $bin['rack_id'] ?? '',
            'bin_id_raw' => $bin['bin_id_raw'] ?? '',
            'bin_product_category' => $bin['bin_product_category'] ?? '',
            'zone_category_count' => $bin['zone_category_count'] ?? 0
        ];
    };
    
    // Process Category 1: Same Product Bins (sort by items_can_fit descending, then by utilization_after)
    usort($sameProductBins, function($a, $b) {
        $itemsA = $a['items_can_fit'] ?? 0;
        $itemsB = $b['items_can_fit'] ?? 0;
        if ($itemsA != $itemsB) return $itemsB <=> $itemsA; // More items first
        
        // If same items, prefer higher utilization after (more efficient)
        $utilA = $a['utilization_after'] ?? 0;
        $utilB = $b['utilization_after'] ?? 0;
        return $utilB <=> $utilA;
    });
    $sameProductResults = array_slice($sameProductBins, 0, 5);
    $sameProductResults = array_map(function($bin, $idx) use ($enrichBin) {
        $enriched = $enrichBin($bin, 'same_product');
        $enriched['rank'] = $idx + 1;
        $enriched['quality_label'] = '⭐ Cùng sản phẩm';
        $enriched['quality_color'] = '#7c3aed';
        $enriched['category'] = 'same_product';
        return $enriched;
    }, $sameProductResults, array_keys($sameProductResults));
    
    // Process Category 2: Same Category Bins (sort by utilization_after to prefer good fill)
    usort($sameCategoryBins, function($a, $b) {
        $utilA = $a['utilization_after'] ?? 0;
        $utilB = $b['utilization_after'] ?? 0;
        
        // Prefer bins that will be 75-95% full (optimal)
        $distA = abs(85 - $utilA);
        $distB = abs(85 - $utilB);
        
        if ($distA != $distB) return $distA <=> $distB;
        
        // If same distance, prefer more items
        $itemsA = $a['items_can_fit'] ?? 0;
        $itemsB = $b['items_can_fit'] ?? 0;
        return $itemsB <=> $itemsA;
    });
    $sameCategoryResults = array_slice($sameCategoryBins, 0, 5);
    $sameCategoryResults = array_map(function($bin, $idx) use ($enrichBin) {
        $enriched = $enrichBin($bin, 'same_category');
        $enriched['rank'] = $idx + 1;
        $enriched['quality_label'] = '🏷️ Cùng loại';
        $enriched['quality_color'] = '#f59e0b';
        $enriched['category'] = 'same_category';
        return $enriched;
    }, $sameCategoryResults, array_keys($sameCategoryResults));
    
    // Process Category 2: High Volume Bins (prioritize zones with same category products)
    usort($emptyBins, function($a, $b) {
        // Priority 1: Zone with more same-category products
        $zoneCatA = $a['zone_category_count'] ?? 0;
        $zoneCatB = $b['zone_category_count'] ?? 0;
        
        if ($zoneCatA != $zoneCatB) {
            return $zoneCatB <=> $zoneCatA; // More same-category products in zone first
        }
        
        // Priority 2: Remaining capacity (more space)
        $remainA = $a['bin_remaining_capacity'] ?? 0;
        $remainB = $b['bin_remaining_capacity'] ?? 0;
        return $remainB <=> $remainA; // More space first
    });
    $highVolumeResults = array_slice($emptyBins, 0, 5);
    $highVolumeResults = array_map(function($bin, $idx) use ($enrichBin) {
        $enriched = $enrichBin($bin, 'high_volume');
        $enriched['rank'] = $idx + 1;
        $enriched['quality_label'] = '📦 Thể tích lớn';
        $enriched['quality_color'] = '#059669';
        $enriched['category'] = 'high_volume';
        return $enriched;
    }, $highVolumeResults, array_keys($highVolumeResults));
    
    // Process Category 3: Fillable Bins (sort by how close to full after, prefer 80-95%)
    usort($partialBins, function($a, $b) {
        $utilA = $a['utilization_after'] ?? 0;
        $utilB = $b['utilization_after'] ?? 0;
        
        // Calculate distance from ideal (85%)
        $distA = abs(85 - $utilA);
        $distB = abs(85 - $utilB);
        
        return $distA <=> $distB; // Closer to 85% first
    });
    $fillableResults = array_slice($partialBins, 0, 5);
    $fillableResults = array_map(function($bin, $idx) use ($enrichBin) {
        $enriched = $enrichBin($bin, 'fillable');
        $enriched['rank'] = $idx + 1;
        $enriched['quality_label'] = '✅ Có thể đầy';
        $enriched['quality_color'] = '#0ea5e9';
        $enriched['category'] = 'fillable';
        return $enriched;
    }, $fillableResults, array_keys($fillableResults));
    
    // Debug product data
    error_log("Product data: " . json_encode([
        'has_product_name' => isset($product['product_name']),
        'has_name' => isset($product['name']),
        'product_name_value' => $product['product_name'] ?? 'not set',
        'name_value' => $product['name'] ?? 'not set',
        'pWidth' => $pWidth ?? 'not set',
        'pDepth' => $pDepth ?? 'not set',
        'pHeight' => $pHeight ?? 'not set'
    ]));
    
    $response = [
        'success' => true,
        'same_product_bins' => $sameProductResults,
        'same_category_bins' => $sameCategoryResults,
        'high_volume_bins' => $highVolumeResults,
        'fillable_bins' => $fillableResults,
        'total_evaluated' => count($bins),
        'counts' => [
            'same_product' => count($sameProductResults),
            'same_category' => count($sameCategoryResults),
            'high_volume' => count($highVolumeResults),
            'fillable' => count($fillableResults)
        ],
        'product_name' => $product['product_name'] ?? $product['name'] ?? 'N/A',
        'product_dimensions' => sprintf(
            "%.1f×%.1f×%.1f cm",
            $pWidth ?? 0,
            $pDepth ?? 0,
            $pHeight ?? 0
        )
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    ob_clean(); // Clean output buffer on error
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
