<?php
/* client/header.php – shared header for all customer-facing pages */
$currentPage = strtolower(basename($_SERVER['PHP_SELF']));
$pageTitle   = $pageTitle ?? 'Lumi Dress – Cho thuê trang phục cao cấp';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Lumi Dress – cho thuê trang phục cao cấp: váy cưới, áo dài, vest nam, váy dạ hội. Sang trọng, đa dạng, giá tốt tại Hà Nội.">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="icon" href="../assets/images/favicon.svg" type="image/svg+xml">
<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<!-- Client CSS -->
<link rel="stylesheet" href="../assets/css/client.css?v=<?= time() ?>">
</head>
<body>

<!-- ========== SITE HEADER ========== -->
<header class="site-header" id="siteHeader">
  <div class="container">
    <div class="header-inner">

      <!-- Logo -->
      <a href="index.php" class="site-logo">
        <!-- <img src="../assets/images/logo-white.svg" alt="Lumi Dress" id="headerLogo" style="filter:invert(1) sepia(1) saturate(3) hue-rotate(220deg) brightness(.6);"> -->
        <div class="site-logo-text">
          Lumi Dress
          <span>Cho thuê trang phục</span>
        </div>
      </a>

      <!-- Desktop nav -->
      <nav>
        <ul class="site-nav">
          <li><a href="index.php"       class="<?= $currentPage==='index.php' ? 'active':'' ?>"><i class="fas fa-home"></i> Trang chủ</a></li>
          <li><a href="products.php"    class="<?= $currentPage==='products.php' ? 'active':'' ?>"><i class="fas fa-shirt"></i> Sản phẩm</a></li>
          <li><a href="contact.php"       class="<?= $currentPage==='contact.php' ? 'active':'' ?>"><i class="fas fa-phone"></i> Liên hệ</a></li>
        </ul>
      </nav>

      <!-- Header actions -->
      <div class="header-actions">
        <!-- CTA Giỏ thuê -->
        <a href="cart.php" class="btn-header-cta">
          <i class="fas fa-cart-shopping"></i> Giỏ thuê
        </a>

        <?php if (!empty($_SESSION['customer'])): ?>
          <div class="user-dropdown-wrap">
            <a href="profile.php" class="btn-header-user" title="Thông tin tài khoản">
              <i class="fas fa-circle-user"></i>
              <span><?= htmlspecialchars($_SESSION['customer']['fullname'] ?? 'Khách hàng') ?></span>
            </a>
            <a href="logout.php" class="btn-header-logout" title="Đăng xuất">
              <i class="fas fa-right-from-bracket"></i>
            </a>
          </div>
        <?php else: ?>
          <!-- Đăng nhập -->
          <a href="login.php" class="btn-header-login">
            <i class="fas fa-user"></i> Đăng nhập
          </a>
        <?php endif; ?>
      </div>

    </div><!-- /header-inner -->
  </div><!-- /container -->
</header>


