<?php
/* client/product.php – trang chi tiết trang phục (?id=...)
 * Chỉ dùng lại: ../config.php, header.php, footer.php, client.css (không chỉnh sửa các file đó). */
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();   // giỏ thuê lưu trong session
date_default_timezone_set('Asia/Ho_Chi_Minh');

/* ---------- Helpers ---------- */
if (!function_exists('color_to_hex')) {
  function color_to_hex(string $name): string {
    $map = ['trắng'=>'#fff9f5','trắng kem'=>'#fdf8ef','đen'=>'#2a2a2a','đỏ'=>'#c0392b','xanh navy'=>'#1e3a5f','xanh'=>'#2980b9','vàng'=>'#f1c40f','hồng'=>'#e91e8c','tím'=>'#8e44ad','xám'=>'#7f8c8d','nâu'=>'#7d4e30','kem'=>'#f5deb3','bạc'=>'#bdc3c7'];
    $key = mb_strtolower(trim($name));
    foreach ($map as $k => $v) { if (str_contains($key, $k)) return $v; }
    return '#c5c5d2';
  }
}

/* ---------- Lấy sản phẩm ---------- */
$id      = (int)($_GET['id'] ?? 0);
$product = null;

if ($id > 0) {
  $stmt = $conn->prepare(
    "SELECT c.*, cat.name AS cat_name
     FROM costumes c
     LEFT JOIN categories cat ON cat.id = c.category_id
     WHERE c.id = ? AND c.status <> 'retired'
     LIMIT 1"
  );
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $product = $stmt->get_result()->fetch_assoc();
}

/* ---------- Không tìm thấy ---------- */
if (!$product) {
  http_response_code(404);
  $pageTitle = 'Không tìm thấy trang phục – Lumi Dress';
  include 'header.php';
  ?>
  <section class="section">
    <div class="container">
      <div class="empty-state">
        <i class="fas fa-shirt"></i>
        <h3>Không tìm thấy trang phục này</h3>
        <p>Sản phẩm có thể đã ngừng cho thuê. <a href="products.php" style="color:var(--primary)">Xem tất cả trang phục</a>.</p>
      </div>
    </div>
  </section>
  <?php
  include 'footer.php';
  exit;
}

/* ---------- Trạng thái & ảnh ---------- */
$stock       = (int)$product['quantity'];
$isAvailable = ($product['status'] === 'available' && $stock > 0);

if ($product['status'] === 'maintenance') { $statusText = 'Đang bảo trì';                $statusClass = 'warn'; }
elseif ($stock <= 0)                      { $statusText = 'Tạm hết';                     $statusClass = 'out';  }
else                                      { $statusText = 'Còn sẵn · ' . $stock . ' bộ'; $statusClass = 'ok';   }

$imgSrc = ($product['image'] && file_exists('../uploads/' . $product['image']))
          ? '../uploads/' . rawurlencode($product['image']) : null;
$isNew  = strtotime($product['created_at']) > strtotime('-30 days');

$desc = $product['description']
        ? strip_tags($product['description'], '<p><br><strong><em><ul><li>') : '';

$catId = (int)($product['category_id'] ?? 0);

/* ---------- Sản phẩm tương tự (ưu tiên cùng danh mục) ---------- */
$st = $conn->prepare(
  "SELECT c.*, cat.name AS cat_name
   FROM costumes c
   LEFT JOIN categories cat ON cat.id = c.category_id
   WHERE c.status = 'available' AND c.id <> ?
   ORDER BY (c.category_id = ?) DESC, RAND()
   LIMIT 4"
);
$st->bind_param('ii', $id, $catId);
$st->execute();
$similar = $st->get_result()->fetch_all(MYSQLI_ASSOC);

/* ---------- Số lượng hiện có trong giỏ (để hiện huy hiệu trên nút "Giỏ thuê") ---------- */
$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
  foreach ($_SESSION['cart'] as $line) { $cartCount += (int)($line['qty'] ?? 0); }
}

/* ---------- Cấu hình truyền sang JS ---------- */
$pdConfig = [
  'id'          => (int)$product['id'],
  'price'       => (float)$product['rental_price'],
  'deposit'     => (float)$product['deposit'],
  'maxQty'      => max(1, $stock),
  'canRent'     => $isAvailable,
  'cartCount'   => $cartCount,
  'ajaxCart'    => 'ajax_cart.php',
  'checkoutUrl' => 'checkout.php',   // ← đổi thành tên file trang thanh toán của bạn làm phần đó
];

$pageTitle = $product['name'] . ' – Lumi Dress';
include 'header.php';
?>
<!-- CSS riêng của trang chi tiết (nạp sau client.css, không sửa client.css) -->
<link rel="stylesheet" href="../assets/css/product-detail.css">

<!-- Breadcrumb -->
<div class="pd-breadcrumb">
  <div class="container">
    <nav aria-label="Breadcrumb">
      <a href="index.php">Trang chủ</a>
      <i class="fas fa-chevron-right" aria-hidden="true"></i>
      <a href="products.php">Sản phẩm</a>
      <?php if (!empty($product['cat_name'])): ?>
      <i class="fas fa-chevron-right" aria-hidden="true"></i>
      <a href="products.php?cat=<?= $catId ?>"><?= htmlspecialchars($product['cat_name']) ?></a>
      <?php endif; ?>
      <i class="fas fa-chevron-right" aria-hidden="true"></i>
      <span><?= htmlspecialchars($product['name']) ?></span>
    </nav>
  </div>
</div>

<!-- Thông tin chính -->
<section class="section pd-section-main">
  <div class="container">
    <div class="pd-wrap">

      <!-- Ảnh -->
      <div class="pd-gallery">
        <div class="pd-main-img">
          <?php if ($imgSrc): ?>
            <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($product['name']) ?>">
          <?php else: ?>
            <div class="pd-img-placeholder" aria-hidden="true"><i class="fas fa-shirt"></i></div>
          <?php endif; ?>
          <div class="product-badges">
            <?php if ($isNew): ?><span class="product-badge badge-new"><i class="fas fa-sparkles"></i> Mới</span><?php endif; ?>
            <?php if (!$isAvailable): ?><span class="product-badge badge-out"><?= htmlspecialchars($statusText) ?></span><?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Thông tin -->
      <div class="pd-info">
        <div class="product-category"><?= htmlspecialchars($product['cat_name'] ?? '—') ?></div>
        <h1 class="pd-title"><?= htmlspecialchars($product['name']) ?></h1>

        <div class="pd-rating-line">
          <span class="pd-stars"><i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i></span>
          <a href="#reviews">Chưa có đánh giá</a>
          <span class="pd-code">Mã: <?= htmlspecialchars($product['code']) ?></span>
        </div>

        <div class="pd-price-box">
          <div class="pd-price">
            <span class="price-amount"><?= format_price($product['rental_price']) ?>₫</span>
            <span class="price-unit">/ ngày thuê</span>
          </div>
          <div class="price-deposit">Đặt cọc: <strong><?= format_price($product['deposit']) ?>₫</strong> (hoàn lại khi trả đồ)</div>
        </div>

        <span class="pd-status pd-status-<?= $statusClass ?>">
          <i class="fas <?= $statusClass === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
          <?= htmlspecialchars($statusText) ?>
        </span>

        <table class="pd-table">
          <tr><th>Kích cỡ</th><td><span class="product-size"><?= htmlspecialchars($product['size']) ?></span></td></tr>
          <?php if (!empty($product['color'])): ?>
          <tr><th>Màu sắc</th>
              <td><span class="product-color-dot" style="background:<?= htmlspecialchars(color_to_hex($product['color'])) ?>;"></span>
                  <?= htmlspecialchars($product['color']) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($product['condition_note'])): ?>
          <tr><th>Tình trạng</th><td><?= htmlspecialchars($product['condition_note']) ?></td></tr>
          <?php endif; ?>
          <tr><th>Danh mục</th><td><?= htmlspecialchars($product['cat_name'] ?? '—') ?></td></tr>
          <tr><th>Số lượng trong kho</th><td><?= $stock ?> bộ</td></tr>
        </table>

        <!-- Chọn ngày thuê -->
        <div class="pd-block">
          <div class="pd-label">Thời gian thuê</div>
          <div class="pd-dates">
            <label class="pd-field">
              <span>Ngày nhận</span>
              <input type="date" id="pdReceive" min="<?= date('Y-m-d') ?>" <?= $isAvailable ? '' : 'disabled' ?>>
            </label>
            <label class="pd-field">
              <span>Ngày trả</span>
              <input type="date" id="pdReturn" min="<?= date('Y-m-d') ?>" <?= $isAvailable ? '' : 'disabled' ?>>
            </label>
          </div>
        </div>

        <!-- Số lượng -->
        <div class="pd-block">
          <div class="pd-label">Số lượng</div>
          <div class="qty-box" role="group" aria-label="Số lượng">
            <button type="button" id="pdMinus" aria-label="Giảm" <?= $isAvailable ? '' : 'disabled' ?>><i class="fas fa-minus"></i></button>
            <input type="number" id="pdQty" value="1" min="1" max="<?= max(1, $stock) ?>" inputmode="numeric" aria-label="Số lượng" <?= $isAvailable ? '' : 'disabled' ?>>
            <button type="button" id="pdPlus" aria-label="Tăng" <?= $isAvailable ? '' : 'disabled' ?>><i class="fas fa-plus"></i></button>
          </div>
        </div>

        <!-- Tóm tắt -->
        <div class="pd-summary" aria-live="polite">
          <div><span>Số ngày thuê</span><strong id="pdDays">—</strong></div>
          <div><span>Tiền thuê</span><strong id="pdRentTotal">—</strong></div>
          <div><span>Tiền cọc</span><strong id="pdDepositTotal">—</strong></div>
          <div class="pd-summary-total"><span>Tạm tính</span><strong id="pdGrand">—</strong></div>
        </div>

        <!-- Nút -->
        <div class="pd-actions">
          <button type="button" class="btn-enquire" id="pdAdd" <?= $isAvailable ? '' : 'disabled' ?>>
            <i class="fas fa-cart-plus"></i> Thêm vào giỏ thuê
          </button>
          <button type="button" class="btn-rent-now" id="pdBuy" <?= $isAvailable ? '' : 'disabled' ?>>
            <i class="fas fa-bolt"></i> Thuê ngay
          </button>
        </div>
        <?php if (!$isAvailable): ?>
          <p class="pd-unavailable">Trang phục này hiện chưa thể đặt thuê. Vui lòng <a href="contact.php?costume=<?= (int)$product['id'] ?>">liên hệ</a> để được tư vấn mẫu tương tự.</p>
        <?php endif; ?>

        <ul class="pd-notes">
          <li><i class="fas fa-phone"></i> Cần tư vấn? Gọi <a href="tel:0987654321">0987 654 321</a></li>
          <li><i class="fas fa-location-dot"></i> Thử đồ tại 12 Chùa Bộc, Kim Liên, Hà Nội</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- Mô tả -->
<section class="section-sm pd-desc-section">
  <div class="container">
    <h2 class="pd-h2">Mô tả sản phẩm</h2>
    <div class="pd-desc">
      <?= $desc !== '' ? $desc : '<p class="text-muted">Chưa có mô tả cho sản phẩm này.</p>' ?>
    </div>
  </div>
</section>

<!-- Đánh giá
     TODO: hiện chưa có bảng đánh giá trong SQL nên chỉ hiển thị trạng thái trống.
     Khi nhóm bổ sung bảng đánh giá, thay khối .rv-empty bên dưới bằng danh sách đánh giá lấy từ database. -->
<section class="section-sm" id="reviews">
  <div class="container">
    <h2 class="pd-h2">Đánh giá của khách hàng</h2>
    <div class="rv-empty">
      <i class="far fa-comment-dots" aria-hidden="true"></i>
      <p>Chưa có đánh giá nào cho trang phục này.</p>
    </div>
  </div>
</section>

<!-- Sản phẩm tương tự -->
<?php if (!empty($similar)): ?>
<section class="section" style="padding-top:48px;">
  <div class="container">
    <div class="section-head" style="margin-bottom:32px;">
      <span class="section-eyebrow">Có thể bạn thích</span>
      <h2 class="section-title">Sản phẩm tương tự</h2>
    </div>
    <div class="product-grid">
      <?php foreach ($similar as $item):
        $sImg = ($item['image'] && file_exists('../uploads/' . $item['image']))
                ? '../uploads/' . htmlspecialchars($item['image']) : null;
        $sNew = strtotime($item['created_at']) > strtotime('-30 days');
      ?>
      <article class="product-card">
        <a class="product-img-wrap" href="product.php?id=<?= (int)$item['id'] ?>" aria-label="<?= htmlspecialchars($item['name']) ?>">
          <?php if ($sImg): ?>
            <img src="<?= $sImg ?>" alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
          <?php else: ?>
            <div style="width:100%;height:100%;display:grid;place-items:center;background:#f4f3f8;color:#ccc;font-size:3rem;" aria-hidden="true"><i class="fas fa-shirt"></i></div>
          <?php endif; ?>
          <div class="product-badges">
            <?php if ($sNew): ?><span class="product-badge badge-new"><i class="fas fa-sparkles"></i> Mới</span><?php endif; ?>
          </div>
        </a>
        <div class="product-body">
          <div class="product-category"><?= htmlspecialchars($item['cat_name'] ?? '—') ?></div>
          <h3 class="product-name"><?= htmlspecialchars($item['name']) ?></h3>
          <div class="product-meta">
            <span class="product-size"><i class="fas fa-ruler-horizontal" style="margin-right:4px;"></i><?= htmlspecialchars($item['size']) ?></span>
            <?php if (!empty($item['color'])): ?>
            <span class="product-color-dot" style="background:<?= htmlspecialchars(color_to_hex($item['color'])) ?>;" title="Màu: <?= htmlspecialchars($item['color']) ?>"></span>
            <small style="font-size:.73rem;color:var(--muted);"><?= htmlspecialchars($item['color']) ?></small>
            <?php endif; ?>
          </div>
          <div class="product-price">
            <span class="price-amount"><?= format_price($item['rental_price']) ?>₫</span>
            <span class="price-unit">/ lần thuê</span>
          </div>
          <div class="price-deposit">Đặt cọc: <strong><?= format_price($item['deposit']) ?>₫</strong></div>
        </div>
        <div class="product-footer">
          <a href="product.php?id=<?= (int)$item['id'] ?>" class="btn-enquire"><i class="fas fa-circle-info"></i> Chi tiết</a>
          <a href="contact.php?costume=<?= (int)$item['id'] ?>" class="btn-rent-now"><i class="fas fa-calendar-check"></i> Đặt thuê</a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<script>window.PD_CONFIG = <?= json_encode($pdConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="../assets/js/product-detail.js"></script>

<?php include 'footer.php'; ?>