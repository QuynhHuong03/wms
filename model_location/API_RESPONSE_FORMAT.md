# 📡 API Response Format - Get Recommendations

## Endpoint
`POST /view/page/manage/receipts/locate/get_recommendations.php`

## Request Body
```json
{
  "product_id": "507f1f77bcf86cd799439011",
  "quantity": 30,
  "unit": "thùng",
  "warehouse_id": "WH001",
  "receipt_id": "REC-2024-001"
}
```

## Response Structure

### Success Response (3 Categories)

```json
{
  "success": true,
  "same_product_bins": [
    {
      "bin_id": "Z1/R1/B2",
      "bin_code": "Z1-R1-B2",
      "quality_score": 0.95,
      "quality_percentage": 95.0,
      "current_utilization": 60.5,
      "utilization_after": 100.0,
      "fit_method": "stacked",
      "items_can_fit": 24,
      "has_same_product": true,
      "current_qty": 10,
      "zone_id": "Z1",
      "rack_id": "R1",
      "bin_id_raw": "B2",
      "rank": 1,
      "quality_label": "⭐ Cùng sản phẩm",
      "quality_color": "#7c3aed",
      "category": "same_product"
    }
  ],
  "high_volume_bins": [
    {
      "bin_id": "Z1/R1/B8",
      "bin_code": "Z1-R1-B8",
      "quality_score": 1.0,
      "quality_percentage": 100.0,
      "current_utilization": 0.0,
      "utilization_after": 60.0,
      "fit_method": "stacked",
      "items_can_fit": 30,
      "has_same_product": false,
      "current_qty": 0,
      "zone_id": "Z1",
      "rack_id": "R1",
      "bin_id_raw": "B8",
      "rank": 1,
      "quality_label": "📦 Thể tích lớn",
      "quality_color": "#059669",
      "category": "high_volume"
    }
  ],
  "fillable_bins": [
    {
      "bin_id": "Z1/R2/B7",
      "bin_code": "Z1-R2-B7",
      "quality_score": 1.0,
      "quality_percentage": 100.0,
      "current_utilization": 45.0,
      "utilization_after": 85.0,
      "fit_method": "horizontal",
      "items_can_fit": 20,
      "has_same_product": false,
      "current_qty": 0,
      "zone_id": "Z1",
      "rack_id": "R2",
      "bin_id_raw": "B7",
      "rank": 1,
      "quality_label": "✅ Có thể đầy",
      "quality_color": "#0ea5e9",
      "category": "fillable"
    }
  ],
  "total_evaluated": 79,
  "counts": {
    "same_product": 3,
    "high_volume": 5,
    "fillable": 5
  },
  "product_name": "Màn máy tính để bàn",
  "product_dimensions": "40.0×50.0×15.0 cm"
}
```

## Response Fields

### Main Response
| Field | Type | Description |
|-------|------|-------------|
| `success` | boolean | Request success status |
| `same_product_bins` | array | Bins already containing same product (max 5) |
| `high_volume_bins` | array | Empty bins with high available volume (max 5) |
| `fillable_bins` | array | Bins that can be filled to near-full (max 5) |
| `total_evaluated` | integer | Total number of bins evaluated |
| `counts` | object | Count of bins in each category |
| `product_name` | string | Name of the product |
| `product_dimensions` | string | Dimensions of product/unit |

### Bin Object Fields
| Field | Type | Description |
|-------|------|-------------|
| `bin_id` | string | Full bin path (Z1/R1/B2) |
| `bin_code` | string | Display code (Z1-R1-B2) |
| `quality_score` | float | Quality score (0.0-1.0) |
| `quality_percentage` | float | Quality score as percentage |
| `current_utilization` | float | Current capacity usage (%) |
| `utilization_after` | float | Capacity after allocation (%) |
| `fit_method` | string | "stacked" or "horizontal" |
| `items_can_fit` | integer | Number of items that can fit |
| `has_same_product` | boolean | Whether bin contains same product |
| `current_qty` | integer | Current quantity in bin |
| `zone_id` | string | Zone identifier |
| `rack_id` | string | Rack identifier |
| `bin_id_raw` | string | Raw bin identifier |
| `rank` | integer | Ranking within category (1-5) |
| `quality_label` | string | Display label for category |
| `quality_color` | string | Hex color for UI display |
| `category` | string | Category type |

### Categories
1. **same_product**: Bins containing the same product
   - Label: "⭐ Cùng sản phẩm"
   - Color: `#7c3aed` (Purple)
   - Sorted by: items_can_fit (desc), then utilization_after (desc)

2. **high_volume**: Empty bins with lots of space
   - Label: "📦 Thể tích lớn"
   - Color: `#059669` (Green)
   - Sorted by: remaining capacity (desc)

3. **fillable**: Bins that can be filled to 80-95%
   - Label: "✅ Có thể đầy"
   - Color: `#0ea5e9` (Blue)
   - Sorted by: distance from 85% utilization (asc)

## Error Response

```json
{
  "success": false,
  "error": "Missing product_id",
  "debug": {
    "input": { }
  }
}
```

## Empty Result

```json
{
  "success": true,
  "same_product_bins": [],
  "high_volume_bins": [],
  "fillable_bins": [],
  "message": "Không có bin phù hợp (tất cả đã đầy hoặc không đủ kích thước)"
}
```

## Notes

- Each category returns **maximum 5 bins**
- Bins containing different products are **excluded** (prevent product mixing)
- Quality score calculation varies by category
- All percentages are rounded to 1 decimal place
- Bins are sorted optimally within each category
