<?php
require 'vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;

$productCode = "BAR1000";  // thay bằng SKU thực tế

$generator = new BarcodeGeneratorPNG();
$barcodeBinary = $generator->getBarcode($productCode, $generator::TYPE_CODE_128);

// Chuyển ảnh PNG sang base64 để nhúng vào <img>
$barcodeBase64 = base64_encode($barcodeBinary);
$barcodeDataUri = 'data:image/png;base64,' . $barcodeBase64;

// Thông tin hiển thị (bạn có thể replace/fetch từ DB)
$productName = "Laptop Asus VivoBook 15";
$brand = "Asus";
$price = "15.000.000₫";
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Barcode - <?=htmlspecialchars($productCode) ?></title>
  <style>
    :root{
      --bg:#f4f6f8;
      --card:#ffffff;
      --accent:#0f6efd;
      --muted:#6b7280;
      --shadow: 0 8px 24px rgba(16,24,40,0.08);
      --radius:12px;
      --max-width:540px;
    }

    html,body{
      height:100%;
      margin:0;
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      background:linear-gradient(180deg,var(--bg),#eef2f6);
      color:#0f172a;
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
    }

    .wrap{
      min-height:100%;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:40px 16px;
      box-sizing:border-box;
    }

    .card{
      width:100%;
      max-width:var(--max-width);
      background:var(--card);
      border-radius:var(--radius);
      box-shadow:var(--shadow);
      padding:20px;
      box-sizing:border-box;
      border:1px solid rgba(15,23,42,0.04);
    }

    .header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      margin-bottom:12px;
    }

    .title{
      font-size:18px;
      font-weight:600;
    }
    .subtitle{
      font-size:13px;
      color:var(--muted);
    }

    .product{
      display:flex;
      gap:14px;
      align-items:center;
      margin-bottom:16px;
    }

    .product .info{
      flex:1;
    }

    .brand{
      font-size:13px;
      color:var(--muted);
    }

    .name{
      font-size:16px;
      font-weight:700;
      margin-top:6px;
    }

    .price{
      font-size:15px;
      color:var(--accent);
      margin-top:6px;
      font-weight:600;
    }

    .barcode-wrap{
      display:flex;
      flex-direction:column;
      align-items:center;
      gap:8px;
      padding:14px;
      background:linear-gradient(180deg, rgba(15,110,253,0.03), rgba(15,110,253,0.01));
      border-radius:10px;
      margin-bottom:14px;
    }

    .barcode-img{
      display:block;
      width:320px;
      max-width:100%;
      height:auto;
      image-rendering:crisp-edges; /* giúp barcode nét hơn */
      border-radius:6px;
    }

    .sku{
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, "Roboto Mono", monospace;
      font-size:14px;
      letter-spacing:1px;
      color:#0b1220;
      margin-top:6px;
      background:rgba(255,255,255,0.6);
      padding:6px 10px;
      border-radius:6px;
      border:1px solid rgba(11,18,32,0.05);
    }

    .actions{
      display:flex;
      gap:8px;
      justify-content:space-between;
      align-items:center;
      margin-top:10px;
    }

    .left-note{
      color:var(--muted);
      font-size:13px;
    }

    .btn{
      border:0;
      background:var(--accent);
      color:white;
      padding:10px 14px;
      border-radius:8px;
      font-weight:600;
      cursor:pointer;
      box-shadow: 0 6px 20px rgba(15,110,253,0.16);
    }

    .btn.ghost{
      background:transparent;
      color:var(--accent);
      border:1px solid rgba(15,110,253,0.12);
      box-shadow:none;
    }

    /* In ấn: bỏ mọi thứ không cần thiết và canh giữa */
    @media print{
      body{ background: #fff; }
      .wrap{ padding:0; }
      .card{ box-shadow:none; border:none; max-width:100%; border-radius:0; }
      .actions, .header .subtitle { display:none; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="header">
        <div>
          <div class="title">Mã vạch (Code 128)</div>
          <div class="subtitle">In/hiển thị mã vạch cho sản phẩm</div>
        </div>
        <div class="subtitle">SKU: <?=htmlspecialchars($productCode) ?></div>
      </div>

      <div class="product">
        <div class="info">
          <div class="brand"><?=htmlspecialchars($brand) ?></div>
          <div class="name"><?=htmlspecialchars($productName) ?></div>
          <div class="price"><?=htmlspecialchars($price) ?></div>
        </div>
        <!-- nếu muốn ảnh sản phẩm, thêm <img> ở đây -->
      </div>

      <div class="barcode-wrap">
        <img class="barcode-img" src="<?=$barcodeDataUri?>" alt="Barcode for <?=htmlspecialchars($productCode)?>" />
        <div class="sku"><?=htmlspecialchars($productCode)?></div>
      </div>

      <div class="actions">
        <div class="left-note">Kích thước barcode có thể thay đổi tuỳ máy in</div>
        <div>
          <button class="btn" onclick="window.print()">In mã (Print)</button>
          <button class="btn ghost" onclick="downloadBarcode()">Tải ảnh</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    function downloadBarcode(){
      // Tạo link download từ data-uri
      const img = document.querySelector('.barcode-img');
      const link = document.createElement('a');
      link.href = img.src;
      // filename: sku-barcode.png
      const sku = "<?= addslashes($productCode) ?>";
      link.download = sku + '-barcode.png';
      document.body.appendChild(link);
      link.click();
      link.remove();
    }
  </script>
</body>
</html>
