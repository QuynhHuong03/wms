# MongoDB NoSQL Schema - WMS (Warehouse Management System)

## Chiến lược thiết kế NoSQL

### 1. Embedded Documents (Nhúng tài liệu)
Sử dụng khi:
- Dữ liệu có mối quan hệ 1-N và thường được truy xuất cùng nhau
- Dữ liệu con không cần truy vấn độc lập
- Kích thước document không vượt quá 16MB

### 2. References (Tham chiếu)
Sử dụng khi:
- Dữ liệu có mối quan hệ N-N
- Dữ liệu con cần truy vấn độc lập
- Tránh trùng lặp dữ liệu

---

## Collections Schema

### 1. Collection: `users`

```javascript
{
  "_id": ObjectId("..."),
  "user_id": "NV001",
  "name": "Nguyễn Văn A",
  "email": "nguyenvana@example.com",
  "password": "hashed_password",
  "gender": "Nam",
  "phone": "0901234567",
  "role_id": 1,                    // Reference to roles
  "warehouse_id": 1,               // Reference to warehouses
  "status": 1,                     // 0: inactive, 1: active
  "first_login": true,
  "created_at": ISODate("2024-01-15T08:00:00Z"),
  "updated_at": ISODate("2024-01-15T08:00:00Z")
}
```

**Indexes:**
```javascript
db.users.createIndex({ "email": 1 }, { unique: true })
db.users.createIndex({ "user_id": 1 }, { unique: true })
db.users.createIndex({ "role_id": 1 })
db.users.createIndex({ "warehouse_id": 1 })
```

---

### 2. Collection: `roles`

```javascript
{
  "_id": ObjectId("..."),
  "role_id": 1,
  "role_name": "Quản lý kho",
  "description": "Quản lý toàn bộ hoạt động kho"
}
```

**Indexes:**
```javascript
db.roles.createIndex({ "role_id": 1 }, { unique: true })
```

---

### 3. Collection: `warehouses`

```javascript
{
  "_id": ObjectId("..."),
  "warehouse_id": "KHO001",
  "warehouse_name": "Kho Tổng",
  "address": {                     // Embedded document
    "street": "123 Đường ABC",
    "city": "Quận 1",
    "province": "TP. Hồ Chí Minh"
  },
  "status": 1,                     // 0: inactive, 1: active
  "warehouse_type": 1,             // 1: Kho tổng, 2: Kho chi nhánh
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

**Indexes:**
```javascript
db.warehouses.createIndex({ "warehouse_id": 1 }, { unique: true })
db.warehouses.createIndex({ "warehouse_type": 1 })
db.warehouses.createIndex({ "status": 1 })
```

---

### 4. Collection: `categories`

```javascript
{
  "_id": ObjectId("..."),
  "category_id": "CAT001",
  "category_name": "Điện tử",
  "category_code": "DT",
  "description": "Các sản phẩm điện tử",
  "status": 1,
  "created_at": ISODate("2024-01-01T00:00:00Z"),
  "updated_at": ISODate("2024-01-01T00:00:00Z")
}
```

**Indexes:**
```javascript
db.categories.createIndex({ "category_id": 1 }, { unique: true })
db.categories.createIndex({ "category_code": 1 })
```

---

### 5. Collection: `suppliers`

```javascript
{
  "_id": ObjectId("..."),
  "supplier_id": "SUP001",
  "supplier_name": "Công ty ABC",
  "contact": "Nguyễn Văn B",
  "email": "contact@abc.com",
  "phone": "0901234567",
  "address": "456 Đường XYZ",
  "status": 1,
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

**Indexes:**
```javascript
db.suppliers.createIndex({ "supplier_id": 1 }, { unique: true })
db.suppliers.createIndex({ "supplier_name": 1 })
```

---

### 6. Collection: `products`

```javascript
{
  "_id": ObjectId("..."),
  "sku": "PRD001",
  "product_name": "Laptop Dell XPS 15",
  "barcode": "1234567890123",
  
  // Embedded references (denormalization for fast read)
  "category": {
    "id": "CAT001",
    "name": "Điện tử"
  },
  "supplier": {
    "id": "SUP001",
    "name": "Công ty ABC"
  },
  
  "baseUnit": "cái",
  "conversionUnits": [             // Embedded array
    {
      "unit": "thùng",
      "factor": 10,
      "is_default": false
    }
  ],
  
  "package_dimensions": {          // Embedded document
    "width": 30,
    "depth": 40,
    "height": 5
  },
  "package_weight": 2.5,
  "volume_per_unit": 6000,
  "stackable": true,
  "max_stack_height": 5,
  "min_stock": 10,
  "purchase_price": 25000000,
  "status": 1,
  "image": "/images/products/prd001.jpg",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

**Indexes:**
```javascript
db.products.createIndex({ "sku": 1 }, { unique: true })
db.products.createIndex({ "barcode": 1 })
db.products.createIndex({ "category.id": 1 })
db.products.createIndex({ "supplier.id": 1 })
db.products.createIndex({ "product_name": "text" })
```

---

### 7. Collection: `locations`

**Thiết kế: Embedded Documents (Zone → Rack → Bin)**

```javascript
{
  "_id": "loc_KHO001",
  "warehouse": {                   // Reference info
    "id": "KHO001",
    "name": "Kho Tổng"
  },
  "name": "Location KHO001",
  "description": "Cấu trúc vị trí kho tổng",
  
  "zones": [                       // Embedded array of zones
    {
      "_id": "Z1",
      "zone_id": "Z1",
      "name": "Zone 1",
      "level": 1,
      "warehouse_id": "KHO001",
      "description": "Khu vực 1",
      
      "racks": [                   // Embedded array of racks
        {
          "rack_id": "R1",
          "name": "Rack 1",
          "level": 2,
          
          "bins": [                // Embedded array of bins
            {
              "id": 1,
              "bin_id": "B1",
              "code": "Z1-R1-B1",
              "dimensions": {
                "width": 100,
                "depth": 100,
                "height": 100
              },
              "capacity": 1000,
              "quantity": 250,
              "current_capacity": 25.5,  // %
              "status": "partial",   // empty, partial, full
              "product_id": "ObjectId(...)"
            }
          ]
        }
      ],
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  ],
  
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

**Indexes:**
```javascript
db.locations.createIndex({ "warehouse.id": 1 })
db.locations.createIndex({ "zones.zone_id": 1 })
db.locations.createIndex({ "zones.racks.bins.bin_id": 1 })
db.locations.createIndex({ "zones.racks.bins.product_id": 1 })
```

**Lý do sử dụng Embedded:**
- Zone, Rack, Bin luôn được truy vấn cùng nhau
- Cấu trúc phân cấp rõ ràng
- Không cần query riêng từng level
- Update bin status nhanh hơn

---

### 8. Collection: `inventory`

```javascript
{
  "_id": ObjectId("..."),
  "warehouse_id": "KHO001",
  "product_id": "ObjectId(...)",
  "product_sku": "PRD001",
  "product_name": "Laptop Dell XPS 15",
  "qty": 50.0,
  
  // Location information (denormalized)
  "zone_id": "Z1",
  "rack_id": "R1",
  "bin_id": "B1",
  
  "receipt_id": "IR0001",
  "received_at": ISODate("2024-01-15T10:30:00Z")
}
```

**Indexes:**
```javascript
db.inventory.createIndex({ "warehouse_id": 1, "product_id": 1 })
db.inventory.createIndex({ "product_id": 1 })
db.inventory.createIndex({ "warehouse_id": 1, "zone_id": 1, "rack_id": 1, "bin_id": 1 })
db.inventory.createIndex({ "receipt_id": 1 })
```

---

### 9. Collection: `batches`

```javascript
{
  "_id": ObjectId("..."),
  "batch_code": "LH0001",
  "product_id": "ObjectId(...)",
  "product_sku": "PRD001",
  "quantity_imported": 100.0,
  "quantity_remaining": 75.0,
  "warehouse_id": "KHO001",
  "import_date": ISODate("2024-01-15T08:00:00Z"),
  "unit_price": 25000000,
  "receipt_id": "IR0001",
  "transaction_id": "IR0001",
  "status": "Còn hàng",           // "Còn hàng", "Đã xuất hết"
  "barcode": "BATCH_LH0001",
  "created_at": ISODate("2024-01-15T08:00:00Z"),
  "updated_at": ISODate("2024-01-15T08:00:00Z")
}
```

**Indexes:**
```javascript
db.batches.createIndex({ "batch_code": 1 }, { unique: true })
db.batches.createIndex({ "barcode": 1 })
db.batches.createIndex({ "product_id": 1, "warehouse_id": 1, "import_date": 1 })
db.batches.createIndex({ "transaction_id": 1 })
db.batches.createIndex({ "quantity_remaining": 1 })
```

---

### 10. Collection: `batch_locations`

```javascript
{
  "_id": ObjectId("..."),
  "batch_code": "LH0001",
  "product_id": "ObjectId(...)",
  "warehouse_id": "KHO001",
  
  // Location information
  "zone_id": "Z1",
  "rack_id": "R1",
  "bin_id": "B1",
  
  "quantity": 25.0,
  "import_date": ISODate("2024-01-15T08:00:00Z"),
  "created_at": ISODate("2024-01-15T08:00:00Z")
}
```

**Indexes:**
```javascript
db.batch_locations.createIndex({ "batch_code": 1 })
db.batch_locations.createIndex({ "warehouse_id": 1, "zone_id": 1, "rack_id": 1, "bin_id": 1 })
db.batch_locations.createIndex({ "product_id": 1 })
```

---

### 11. Collection: `transactions`

**Bao gồm cả Receipt, Export, Request (Polymorphic Pattern)**

```javascript
{
  "_id": ObjectId("..."),
  "transaction_id": "IR0001",
  "transaction_type": "import",    // import, export, goods_request, transfer
  "warehouse_id": "KHO001",
  "status": 2,                     // 0: pending, 1: approved, 2: completed, 3: rejected
  "created_by": "NV001",
  "created_at": ISODate("2024-01-15T08:00:00Z"),
  "approved_by": "NV002",
  "approved_at": ISODate("2024-01-15T09:00:00Z"),
  "note": "Nhập hàng đợt 1",
  
  "items": [                       // Embedded array
    {
      "product_id": "ObjectId(...)",
      "product_sku": "PRD001",
      "product_name": "Laptop Dell XPS 15",
      "quantity": 50,
      "unit": "cái",
      "unit_price": 25000000,
      "total": 1250000000
    }
  ],
  
  // Specific fields for goods_request type
  "details": [
    {
      "product_id": "ObjectId(...)",
      "quantity": 20,
      "unit": "cái",
      "conversion_factor": 1
    }
  ]
}
```

**Indexes:**
```javascript
db.transactions.createIndex({ "transaction_id": 1 }, { unique: true })
db.transactions.createIndex({ "transaction_type": 1, "status": 1 })
db.transactions.createIndex({ "warehouse_id": 1, "created_at": -1 })
db.transactions.createIndex({ "created_by": 1 })
db.transactions.createIndex({ "items.product_id": 1 })
```

---

### 12. Collection: `inventory_sheets`

```javascript
{
  "_id": ObjectId("..."),
  "sheet_code": "PKK001",
  "warehouse_id": "KHO001",
  "created_by": "NV001",
  "created_at": ISODate("2024-01-20T08:00:00Z"),
  "status": 1,                     // 0: pending, 1: approved, 2: rejected
  
  "items": [                       // Embedded array
    {
      "product_id": "ObjectId(...)",
      "product_sku": "PRD001",
      "product_name": "Laptop Dell XPS 15",
      "system_qty": 50,
      "actual_qty": 48,
      "difference": -2
    }
  ],
  
  "count_date": ISODate("2024-01-20T00:00:00Z"),
  "approved_by": "NV002",
  "approved_at": ISODate("2024-01-20T10:00:00Z"),
  
  "locations": {                   // Location adjustments
    "Z1": {
      "R1": {
        "B1": {
          "adjustments": [
            {
              "product_id": "ObjectId(...)",
              "difference": -2
            }
          ]
        }
      }
    }
  }
}
```

**Indexes:**
```javascript
db.inventory_sheets.createIndex({ "sheet_code": 1 }, { unique: true })
db.inventory_sheets.createIndex({ "warehouse_id": 1, "created_at": -1 })
db.inventory_sheets.createIndex({ "status": 1 })
db.inventory_sheets.createIndex({ "items.product_id": 1 })
```

---

### 13. Collection: `inventory_movements`

```javascript
{
  "_id": ObjectId("..."),
  "warehouse_id": "KHO001",
  "product_id": "ObjectId(...)",
  "transaction_id": "IR0001",
  "movement_type": "IN",           // IN, OUT, ADJUST
  "qty": 50.0,
  "created_by": "NV001",
  "created_at": ISODate("2024-01-15T10:30:00Z")
}
```

**Indexes:**
```javascript
db.inventory_movements.createIndex({ "transaction_id": 1 })
db.inventory_movements.createIndex({ "warehouse_id": 1, "created_at": -1 })
db.inventory_movements.createIndex({ "product_id": 1, "created_at": -1 })
db.inventory_movements.createIndex({ "movement_type": 1 })
```

---

## Chiến lược Denormalization

### 1. Product trong Inventory
```javascript
// Thay vì chỉ lưu product_id
"product_id": "ObjectId(...)"

// Lưu thêm thông tin thường dùng (denormalization)
"product_id": "ObjectId(...)",
"product_sku": "PRD001",
"product_name": "Laptop Dell XPS 15"
```

**Lợi ích:**
- Giảm số lượng join/lookup
- Tăng tốc độ query
- Trade-off: Cần update nhiều nơi khi product thay đổi

---

### 2. Category & Supplier trong Product
```javascript
"category": {
  "id": "CAT001",
  "name": "Điện tử"
},
"supplier": {
  "id": "SUP001",
  "name": "Công ty ABC"
}
```

**Lợi ích:**
- Không cần lookup khi hiển thị danh sách sản phẩm
- Category/Supplier name hiếm khi thay đổi

---

## Aggregation Pipeline Examples

### 1. Tính tổng tồn kho theo sản phẩm trong kho

```javascript
db.inventory.aggregate([
  {
    $match: { "warehouse_id": "KHO001" }
  },
  {
    $group: {
      _id: "$product_id",
      totalQty: { $sum: "$qty" },
      locations: {
        $push: {
          zone: "$zone_id",
          rack: "$rack_id",
          bin: "$bin_id",
          qty: "$qty"
        }
      }
    }
  }
])
```

### 2. Lấy sản phẩm dưới mức min_stock

```javascript
db.products.aggregate([
  {
    $lookup: {
      from: "inventory",
      let: { prod_id: "$_id" },
      pipeline: [
        {
          $match: {
            $expr: {
              $and: [
                { $eq: ["$product_id", "$$prod_id"] },
                { $eq: ["$warehouse_id", "KHO001"] }
              ]
            }
          }
        },
        {
          $group: {
            _id: null,
            total: { $sum: "$qty" }
          }
        }
      ],
      as: "stock_info"
    }
  },
  {
    $addFields: {
      current_stock: { $ifNull: [{ $arrayElemAt: ["$stock_info.total", 0] }, 0] }
    }
  },
  {
    $match: {
      $expr: { $lt: ["$current_stock", "$min_stock"] }
    }
  }
])
```

### 3. Lấy lịch sử xuất nhập của sản phẩm

```javascript
db.inventory_movements.aggregate([
  {
    $match: {
      "product_id": ObjectId("..."),
      "warehouse_id": "KHO001"
    }
  },
  {
    $lookup: {
      from: "transactions",
      localField: "transaction_id",
      foreignField: "transaction_id",
      as: "transaction_info"
    }
  },
  {
    $unwind: "$transaction_info"
  },
  {
    $sort: { "created_at": -1 }
  },
  {
    $limit: 50
  }
])
```

---

## Performance Optimization

### 1. Compound Indexes
```javascript
// Inventory by warehouse and product
db.inventory.createIndex({ 
  "warehouse_id": 1, 
  "product_id": 1,
  "qty": 1
})

// Transactions by type, status, and date
db.transactions.createIndex({ 
  "transaction_type": 1, 
  "status": 1,
  "created_at": -1
})
```

### 2. Text Search Index
```javascript
db.products.createIndex({ 
  "product_name": "text",
  "sku": "text",
  "barcode": "text"
})
```

### 3. TTL Index (Optional - for temp data)
```javascript
// Auto-delete old movements after 2 years
db.inventory_movements.createIndex(
  { "created_at": 1 },
  { expireAfterSeconds: 63072000 }
)
```

---

## Backup Strategy

### 1. Full Backup (Daily)
```bash
mongodump --db=wms --out=/backup/full/$(date +%Y%m%d)
```

### 2. Incremental Backup (Hourly - Oplog)
```bash
mongodump --db=wms --oplog --out=/backup/incremental/$(date +%Y%m%d_%H)
```

### 3. Important Collections Backup (Real-time)
```bash
mongodump --db=wms --collection=transactions --out=/backup/transactions/
mongodump --db=wms --collection=inventory --out=/backup/inventory/
```

---

## Data Migration Script Example

### Convert từ SQL sang MongoDB

```javascript
// users_migration.js
const users = db.getSiblingDB('old_db').users.find().toArray();

users.forEach(user => {
  db.users.insertOne({
    user_id: user.user_id,
    name: user.name,
    email: user.email,
    password: user.password,
    gender: user.gender,
    phone: user.phone,
    role_id: parseInt(user.role_id),
    warehouse_id: parseInt(user.warehouse_id),
    status: parseInt(user.status),
    first_login: user.first_login === 1,
    created_at: new Date(user.created_at),
    updated_at: new Date(user.updated_at)
  });
});
```

---

## Notes

### Embedded vs Referenced Decision Tree

```
Dữ liệu có kích thước lớn/không giới hạn?
├─ YES → Reference
└─ NO → Tiếp tục

Dữ liệu có cần truy vấn độc lập?
├─ YES → Reference  
└─ NO → Tiếp tục

Dữ liệu thay đổi thường xuyên?
├─ YES → Reference
└─ NO → Tiếp tục

Dữ liệu luôn được đọc cùng parent?
├─ YES → Embedded
└─ NO → Reference
```

### Current Implementation
- ✅ **Embedded**: Location (Zone → Rack → Bin), Transaction items
- ✅ **Referenced**: User ↔ Role, Product ↔ Category/Supplier
- ✅ **Denormalized**: Product info in Inventory, Category/Supplier in Product
