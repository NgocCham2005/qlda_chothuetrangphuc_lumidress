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
<link rel="stylesheet" href="../assets/css/client.css">
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

        <!-- Hamburger (mobile) -->
        <button class="nav-toggle" id="navToggle" aria-label="Mở menu">
          <i class="fas fa-bars"></i>
        </button>
      </div>

    </div><!-- /header-inner -->
  </div><!-- /container -->
</header>

<!-- ========== MOBILE MENU ========== -->
<div class="mobile-menu" id="mobileMenu" role="dialog" aria-modal="true" aria-label="Menu điều hướng">
  <div class="mobile-menu-panel">
    <button class="mobile-menu-close" id="mobileMenuClose" aria-label="Đóng menu"><i class="fas fa-times"></i></button>

    <a href="index.php"      class="mobile-nav-link <?= $currentPage==='index.php'      ? 'active':'' ?>"><i class="fas fa-home"></i> Trang chủ</a>
    <a href="products.php"   class="mobile-nav-link <?= $currentPage==='products.php'   ? 'active':'' ?>"><i class="fas fa-shirt"></i> Sản phẩm</a>
    <a href="about.php"      class="mobile-nav-link <?= $currentPage==='about.php'      ? 'active':'' ?>"><i class="fas fa-circle-info"></i> Giới thiệu</a>
    <a href="cart.php"       class="mobile-nav-link <?= $currentPage==='cart.php'       ? 'active':'' ?>"><i class="fas fa-cart-shopping"></i> Giỏ thuê</a>
    <hr style="border-color:var(--line);margin:12px 0;">
    <a href="../admin/" class="mobile-nav-link" style="opacity:.55;font-size:.82rem;"><i class="fas fa-lock"></i> Quản trị viên</a>
  </div>
</div>


