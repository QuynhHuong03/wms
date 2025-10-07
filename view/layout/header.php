<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Header</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    .header-bar {
      background: #fff;
      border-bottom: 1px solid #e5e7eb;
      padding: 10px 40px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .header-date {
      font-size: 15px;
      color: #4b5563;
    }
    .header-greeting {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .header-greeting h5 {
      margin: 0;
      font-weight: 600;
      color: #111827;
    }
    .header-greeting p {
      margin: 0;
      font-size: 14px;
      color: #6b7280;
    }
    .icon-box {
      background: #e6effaff;
      padding: 15px;
      border-radius: 25%;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .icon-box i {
      font-size: 30px;
      color: #6366f1;
    }
    .fa-sun.morning {
      color: #FFA500;
      font-size: 32px;
    }
    .afternoon-icon {
      position: relative;
      display: inline-block;
      width: 40px; 
      height: 40px;
    }
    .afternoon-icon .sun {
      color: #FFA500;
      font-size: 32px;
      position: absolute;
      top: 0;
      left: 0;
      z-index: 1;
    }
    .afternoon-icon .cloud {
      color: #ffffff;
      font-size: 28px;
      position: absolute;
      left: 10px;
      top: 10px;
      z-index: 2;
    }
    .fa-moon.evening {
      color: #6366f1;
      font-size: 32px;
      transform: rotate(-25deg); /* nghiêng sang trái 20 độ */
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
  <header class="header-bar">
    <div class="header-date">
      <?php 
        setlocale(LC_TIME, 'vi_VN.UTF-8');
        echo strftime("%A, %d tháng %m, %Y"); 
      ?>
    </div>
    <div class="header-greeting">
      <?php 
        date_default_timezone_set("Asia/Ho_Chi_Minh"); 
        $hour = date("H");

        if ($hour >= 5 && $hour < 12) {
          $greeting = "Chào buổi sáng";
          $iconHtml = '<i class="fa-solid fa-sun morning"></i>';
        } elseif ($hour >= 12 && $hour < 18) {
          $greeting = "Chào buổi chiều";
          $iconHtml = '<span class="afternoon-icon">
                         <i class="fa-solid fa-sun sun"></i>
                         <i class="fa-solid fa-cloud cloud"></i>
                       </span>';
        } else {
          $greeting = "Chào buổi tối";
          $iconHtml = '<i class="fa-solid fa-moon evening"></i>';
        }
      ?>

      <div>
        <h5><?php echo $greeting; ?></h5>
        <p>Chúc bạn có một ngày tuyệt vời!</p>
      </div>
      <div class="icon-box">
        <?php echo $iconHtml; ?>
      </div>
    </div>
  </header>
</body>
</html>
