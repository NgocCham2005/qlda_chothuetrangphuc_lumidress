<?php
require_once '../config.php';
require_once '../customer_auth.php';
$pageTitle = 'Lumi Dress';
$isCustomerLoggedIn = customer_logged_in();

// --- Fetch categories ---
$cats = $conn->query("SELECT * FROM categories ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// --- Fetch featured products (available, up to 8) ---
$featuredStmt = $conn->prepare(
  "SELECT c.*, cat.name AS cat_name
   FROM costumes c
   LEFT JOIN categories cat ON cat.id = c.category_id
   WHERE c.status = 'available'
   ORDER BY c.id DESC
   LIMIT 8"
);
$featuredStmt->execute();
$featured = $featuredStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- Stats ---
$totalCostumes = (int)$conn->query("SELECT COUNT(*) n FROM costumes WHERE status='available'")->fetch_assoc()['n'];
$totalCats     = (int)$conn->query("SELECT COUNT(*) n FROM categories")->fetch_assoc()['n'];

include 'header.php';
?>

<!-- ============================================================
     HERO BANNER
     ============================================================ -->
<section class="hero" aria-label="Banner chào mừng">
  <div class="hero-bg-shapes" aria-hidden="true"></div>
  <div class="container">
    <div class="hero-inner">

      <!-- Text -->
      <div class="hero-text">
        <div class="hero-badge" aria-label="Nhãn ưu đãi">
          <i class="fas fa-star" aria-hidden="true"></i>
          Bộ sưu tập mới 2026
        </div>
        <h1 class="hero-title">
          Tỏa sáng trong<br>
          <em>khoảnh khắc đặc biệt</em><br>
          của bạn
        </h1>
        <p class="hero-desc">
          Lumi Dress mang đến những bộ trang phục cao cấp — váy cưới, áo dài, vest nam, váy dạ hội —
          được chăm chút tỉ mỉ để bạn luôn rạng rỡ trong mọi dịp trọng đại.
        </p>
        <div class="hero-actions">
          <a href="products.php" class="btn-hero-primary">
            <i class="fas fa-shirt" aria-hidden="true"></i>
            Khám phá trang phục
          </a>
          <a href="tel:0987654321" class="btn-hero-secondary">
            <i class="fas fa-phone" aria-hidden="true"></i>
            Liên hệ tư vấn
          </a>
        </div>
        <div class="hero-stats" aria-label="Số liệu tổng quan">
          <div class="hero-stat">
            <strong><?= $totalCostumes ?>+</strong>
            <span>Trang phục</span>
          </div>
          <div class="hero-stat">
            <strong><?= $totalCats ?>+</strong>
            <span>Danh mục</span>
          </div>
          <div class="hero-stat">
            <strong>500+</strong>
            <span>Khách hài lòng</span>
          </div>
        </div>
      </div>

      <!-- Visual -->
      <div class="hero-visual" aria-hidden="true">
        <div class="hero-image-wrap">
          <?php
          // Use first featured costume image if available, else placeholder
          $heroImg = $featured[0]['image'] ?? null;
          if ($heroImg && file_exists('../uploads/'.$heroImg)): ?>
            <img src="../uploads/<?= htmlspecialchars($heroImg) ?>" alt="Trang phục nổi bật">
          <?php else: ?>
            <div class="hero-img-placeholder" aria-hidden="true">
              <i class="fas fa-vest-patches"></i>
            </div>
          <?php endif; ?>
          <!-- Floating info card -->
          <div class="hero-card-float" aria-hidden="true">
            <div class="hero-card-float-icon"><i class="fas fa-award"></i></div>
            <div>
              <strong>Chất lượng đảm bảo</strong>
              <span>Vệ sinh &amp; kiểm định trước mỗi lần thuê</span>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /hero-inner -->
  </div><!-- /container -->
</section>

<!-- ============================================================
     CATEGORY QUICK LINKS
     ============================================================ -->
<section class="section-sm" aria-labelledby="cats-title">
  <div class="container">
    <div class="section-head">
      <span class="section-eyebrow">Danh mục</span>
      <h2 class="section-title" id="cats-title">Khám phá theo loại trang phục</h2>
      <p class="section-desc">Chọn loại trang phục phù hợp với sự kiện của bạn</p>
    </div>

    <div class="category-filter" role="list">
      <a href="products.php" class="cat-chip active" role="listitem">
        <i class="fas fa-th-large" aria-hidden="true"></i> Tất cả
      </a>
      <?php foreach ($cats as $cat): ?>
      <a href="products.php?cat=<?= $cat['id'] ?>" class="cat-chip" role="listitem">
        <?php
        $icons = [1=>'fa-champagne-glasses',2=>'fa-user-tie',3=>'fa-person-dress',4=>'fa-star'];
        $icon  = $icons[$cat['id']] ?? 'fa-shirt';
        ?>
        <i class="fas <?= $icon ?>" aria-hidden="true"></i>
        <?= htmlspecialchars($cat['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================================================
     FEATURED PRODUCTS
     ============================================================ -->
<section class="section" id="featured" aria-labelledby="featured-title">
  <div class="container">
    <div class="section-head">
      <span class="section-eyebrow">Nổi bật</span>
      <h2 class="section-title" id="featured-title">Trang phục được yêu thích nhất</h2>
      <p class="section-desc">Những bộ trang phục được khách hàng lựa chọn nhiều nhất — sang trọng, đa dạng và vừa vặn hoàn hảo.</p>
    </div>

    <?php if (empty($featured)): ?>
    <div class="empty-state" aria-live="polite">
      <i class="fas fa-shirt" aria-hidden="true"></i>
      <h3>Chưa có trang phục nào</h3>
      <p>Vui lòng quay lại sau hoặc liên hệ với chúng tôi để được tư vấn.</p>
    </div>
    <?php else: ?>
    <div class="product-grid" role="list">
      <?php foreach ($featured as $item): ?>
      <?php
        $imgSrc  = $item['image'] && file_exists('../uploads/'.$item['image'])
                   ? '../uploads/'.htmlspecialchars($item['image'])
                   : null;
        $isNew   = strtotime($item['created_at']) > strtotime('-30 days');
      ?>
      <article class="product-card" role="listitem">
        <!-- Image -->
        <div class="product-img-wrap">
          <?php if ($imgSrc): ?>
            <img src="<?= $imgSrc ?>"
                 alt="<?= htmlspecialchars($item['name']) ?>"
                 loading="lazy">
          <?php else: ?>
            <div style="width:100%;height:100%;display:grid;place-items:center;background:#f4f3f8;color:#ccc;font-size:3rem;" aria-hidden="true">
              <i class="fas fa-shirt"></i>
            </div>
          <?php endif; ?>

          <!-- Badges -->
          <div class="product-badges" aria-label="Nhãn sản phẩm">
            <?php if ($isNew): ?>
              <span class="product-badge badge-new"><i class="fas fa-sparkles" aria-hidden="true"></i> Mới</span>
            <?php endif; ?>
          </div>

          <!-- Overlay -->
          <div class="product-overlay" aria-hidden="true">
            <button class="btn-quick-view"
                    data-id="<?= $item['id'] ?>"
                    aria-label="Xem nhanh <?= htmlspecialchars($item['name']) ?>">
              <i class="fas fa-eye" aria-hidden="true"></i> Xem nhanh
            </button>
          </div>
        </div>

        <!-- Body -->
        <div class="product-body">
          <div class="product-category"><?= htmlspecialchars($item['cat_name'] ?? '—') ?></div>
          <h3 class="product-name"><?= htmlspecialchars($item['name']) ?></h3>
          <div class="product-meta">
            <span class="product-size" title="Kích cỡ">
              <i class="fas fa-ruler-horizontal" aria-hidden="true" style="margin-right:4px;"></i>
              <?= htmlspecialchars($item['size']) ?>
            </span>
            <?php if (!empty($item['color'])): ?>
            <span class="product-color-dot"
                  style="background:<?= htmlspecialchars(color_to_hex($item['color'])) ?>;"
                  title="Màu: <?= htmlspecialchars($item['color']) ?>"></span>
            <small class="text-muted" style="font-size:.73rem;"><?= htmlspecialchars($item['color']) ?></small>
            <?php endif; ?>
          </div>
          <div class="product-price">
            <span class="price-amount"><?= format_price($item['rental_price']) ?>₫</span>
            <span class="price-unit">/ lần thuê</span>
          </div>
          <div class="price-deposit">Đặt cọc: <strong><?= format_price($item['deposit']) ?>₫</strong></div>
        </div>

        <!-- Footer -->
        <div class="product-footer">
          <a href="product.php?id=<?= $item['id'] ?>" class="btn-enquire">
            <i class="fas fa-circle-info" aria-hidden="true"></i> Chi tiết
          </a>
          <a href="#"
             class="btn-rent-now js-featured-rent"
             data-id="<?= (int)$item['id'] ?>"
             data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
             data-price="<?= format_price($item['rental_price']) ?>"
             data-deposit="<?= format_price($item['deposit']) ?>">
            <i class="fas fa-calendar-check" aria-hidden="true"></i> Đặt thuê
          </a>
        </div>
      </article>
      <?php endforeach; ?>
    </div><!-- /product-grid -->

    <!-- View all -->
    <div style="text-align:center;margin-top:44px;">
      <a href="products.php" class="btn-header-cta" style="min-height:50px;padding:0 32px;font-size:.95rem;border-radius:14px;">
        <i class="fas fa-shirt" aria-hidden="true"></i> Xem tất cả trang phục
      </a>
    </div>
    <?php endif; ?>

  </div><!-- /container -->
</section>

<!-- ============================================================
     FEATURES / WHY CHOOSE US
     ============================================================ -->
<section class="section features-bg" aria-labelledby="features-title">
  <div class="container">
    <div class="section-head">
      <span class="section-eyebrow">Tại sao chọn chúng tôi</span>
      <h2 class="section-title" id="features-title">Trải nghiệm thuê trang phục khác biệt</h2>
      <p class="section-desc">Mỗi dịch vụ đều được thiết kế để bạn cảm thấy tự tin và thoải mái nhất.</p>
    </div>

    <div class="features-grid" role="list">
      <div class="feature-card" role="listitem">
        <div class="feature-icon icon-bg-violet" aria-hidden="true"><i class="fas fa-award"></i></div>
        <h3 class="feature-title">Chất lượng đảm bảo</h3>
        <p class="feature-desc">Tất cả trang phục được vệ sinh kỹ lưỡng, kiểm định và bảo quản chuyên nghiệp trước mỗi lần cho thuê.</p>
      </div>
      <div class="feature-card" role="listitem">
        <div class="feature-icon icon-bg-gold" aria-hidden="true"><i class="fas fa-tags"></i></div>
        <h3 class="feature-title">Giá cả hợp lý</h3>
        <p class="feature-desc">Thuê trang phục cao cấp với chi phí tiết kiệm hơn nhiều so với mua mới. Minh bạch, không phát sinh.</p>
      </div>
      <div class="feature-card" role="listitem">
        <div class="feature-icon icon-bg-rose" aria-hidden="true"><i class="fas fa-ruler-combined"></i></div>
        <h3 class="feature-title">Đa dạng kích cỡ &amp; mẫu</h3>
        <p class="feature-desc">Hàng trăm mẫu trang phục đa dạng từ S đến XL, phù hợp với mọi vóc dáng và phong cách riêng.</p>
      </div>
      <div class="feature-card" role="listitem">
        <div class="feature-icon icon-bg-green" aria-hidden="true"><i class="fas fa-headset"></i></div>
        <h3 class="feature-title">Tư vấn tận tâm</h3>
        <p class="feature-desc">Đội ngũ tư vấn nhiệt tình hỗ trợ bạn chọn trang phục phù hợp từ 8:00 đến 20:00 mỗi ngày.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     HOW IT WORKS
     ============================================================ -->
<section class="section" aria-labelledby="steps-title">
  <div class="container">
    <div class="section-head">
      <span class="section-eyebrow">Quy trình</span>
      <h2 class="section-title" id="steps-title">Thuê trang phục chỉ 4 bước</h2>
      <p class="section-desc">Quy trình đơn giản, nhanh chóng để bạn có được bộ trang phục ưng ý.</p>
    </div>

    <div class="steps-grid" role="list">
      <div class="step-item" role="listitem">
        <div class="step-num" aria-hidden="true">1</div>
        <h3 class="step-title">Chọn trang phục</h3>
        <p class="step-desc">Duyệt bộ sưu tập online hoặc đến trực tiếp cửa hàng để chọn bộ ưng ý nhất.</p>
      </div>
      <div class="step-item" role="listitem">
        <div class="step-num" aria-hidden="true">2</div>
        <h3 class="step-title">Đặt lịch &amp; đặt cọc</h3>
        <p class="step-desc">Chọn ngày nhận và trả đồ, xác nhận đặt cọc để giữ chỗ.</p>
      </div>
      <div class="step-item" role="listitem">
        <div class="step-num" aria-hidden="true">3</div>
        <h3 class="step-title">Nhận trang phục</h3>
        <p class="step-desc">Đến cửa hàng nhận đồ và thử trang phục, chúng tôi hỗ trợ điều chỉnh vừa vặn.</p>
      </div>
      <div class="step-item" role="listitem">
        <div class="step-num" aria-hidden="true">4</div>
        <h3 class="step-title">Trả &amp; hoàn cọc</h3>
        <p class="step-desc">Trả trang phục đúng hẹn và nhận lại tiền cọc. Nhanh gọn, không rắc rối.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     TESTIMONIALS
     ============================================================ -->
<section class="section testimonials-bg" aria-labelledby="testimonials-title">
  <div class="container">
    <div class="section-head">
      <span class="section-eyebrow">Đánh giá khách hàng</span>
      <h2 class="section-title" id="testimonials-title">Khách hàng nói gì về chúng tôi</h2>
      <p class="section-desc">Hơn 500 khách hàng đã tin tưởng và hài lòng với dịch vụ của Lumi Dress.</p>
    </div>

    <div class="testimonial-grid" role="list">
      <article class="testimonial-card" role="listitem">
        <div class="testimonial-stars" aria-label="5 sao">
          <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
        <p class="testimonial-text">"Váy cưới rất đẹp và chất lượng vượt kỳ vọng! Nhân viên tư vấn nhiệt tình, giúp mình chọn được chiếc váy hoàn hảo nhất cho ngày trọng đại."</p>
        <div class="testimonial-author">
          <div class="author-avatar" aria-hidden="true"><i class="fas fa-user"></i></div>
          <div>
            <span class="author-name">Nguyễn Minh Anh</span>
            <span class="author-event">Lễ cưới tháng 10/2026</span>
          </div>
        </div>
      </article>

      <article class="testimonial-card" role="listitem">
        <div class="testimonial-stars" aria-label="5 sao">
          <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
        <p class="testimonial-text">"Thuê vest cho đám cưới của em gái, vest đẹp, vừa vặn, giá rất hợp lý. Quy trình đặt thuê đơn giản và nhanh chóng. Mình sẽ tiếp tục ủng hộ!"</p>
        <div class="testimonial-author">
          <div class="author-avatar" aria-hidden="true"><i class="fas fa-user"></i></div>
          <div>
            <span class="author-name">Trần Hoàng Nam</span>
            <span class="author-event">Lễ cưới tháng 9/2026</span>
          </div>
        </div>
      </article>

      <article class="testimonial-card" role="listitem">
        <div class="testimonial-stars" aria-label="5 sao">
          <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
        <p class="testimonial-text">"Bộ áo dài đỏ thêu hoa sen thực sự tuyệt vời, chụp ảnh cực đẹp! Lumi Dress là địa chỉ thuê trang phục mình sẽ giới thiệu cho bạn bè."</p>
        <div class="testimonial-author">
          <div class="author-avatar" aria-hidden="true"><i class="fas fa-user"></i></div>
          <div>
            <span class="author-name">Lê Thu Hương</span>
            <span class="author-event">Chụp ảnh kỷ yếu tháng 8/2026</span>
          </div>
        </div>
      </article>
    </div>
  </div>
</section>

<!-- ============================================================
     CTA BANNER
     ============================================================ -->
<section class="section-sm" aria-labelledby="cta-title">
  <div class="container">
    <div class="cta-banner" aria-label="Lời kêu gọi hành động">
      <div class="cta-text">
        <span class="cta-eyebrow">Sẵn sàng tỏa sáng?</span>
        <h2 class="cta-title" id="cta-title">Đặt lịch tư vấn miễn phí ngay hôm nay</h2>
        <p class="cta-desc">Liên hệ với chúng tôi để được tư vấn chọn trang phục phù hợp nhất với dịp của bạn.</p>
      </div>
      <div class="cta-actions">
        <a href="contact.php" class="btn-cta-gold">
          <i class="fas fa-calendar-check" aria-hidden="true"></i> Đặt lịch ngay
        </a>
        <a href="tel:0987654321" class="btn-cta-outline">
          <i class="fas fa-phone" aria-hidden="true"></i> Gọi ngay
        </a>
      </div>
    </div>
  </div>
</section>

<?php
// Quick-view modal (populated via JS)
?>
<!-- ========== RENT MODAL ========== -->
<div class="modal-backdrop-custom" id="rentModal" role="dialog" aria-modal="true" aria-label="Đặt thuê trang phục" aria-hidden="true">
  <div class="modal-box" style="max-width:520px;grid-template-columns:1fr;">
    <button class="modal-close-btn" id="rentModalClose" type="button" aria-label="Đóng"><i class="fas fa-times"></i></button>
    <div class="modal-info-col" style="padding:28px 28px 24px;">
      <div class="modal-category">Đặt thuê nhanh</div>
      <h2 class="modal-name" id="rentModalName" style="font-size:1.25rem;margin-bottom:6px;">—</h2>
      <div id="rentModalMeta" style="color:var(--muted);font-size:.9rem;margin-bottom:18px;"></div>
      <form id="rentModalForm">
        <input type="hidden" name="id" id="rentModalId" value="">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <label style="display:flex;flex-direction:column;gap:6px;font-size:.88rem;color:var(--ink);">
            <span>Ngày nhận</span>
            <input type="date" name="receive" id="rentReceive" required style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;">
          </label>
          <label style="display:flex;flex-direction:column;gap:6px;font-size:.88rem;color:var(--ink);">
            <span>Ngày trả</span>
            <input type="date" name="return" id="rentReturn" required style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;">
          </label>
        </div>
        <label style="display:flex;flex-direction:column;gap:6px;font-size:.88rem;color:var(--ink);margin-bottom:14px;max-width:180px;">
          <span>Số lượng</span>
          <input type="number" name="qty" id="rentQty" value="1" min="1" style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;">
        </label>
        <div id="rentModalMsg" style="margin-bottom:12px;font-size:.9rem;"></div>
        <div class="modal-actions">
          <button type="button" class="btn-enquire" id="rentModalCancel" style="flex:1;min-height:46px;">Hủy</button>
          <button type="submit" class="btn-rent-now" id="rentModalSubmit" style="flex:1;min-height:46px;"><i class="fas fa-calendar-check"></i> Xác nhận đặt thuê</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ========== QUICK VIEW MODAL ========== -->
<div class="modal-backdrop-custom" id="quickViewModal" role="dialog" aria-modal="true" aria-label="Xem nhanh trang phục" aria-hidden="true">
  <div class="modal-box">
    <button class="modal-close-btn" id="quickViewClose" aria-label="Đóng"><i class="fas fa-times"></i></button>
    <div class="modal-img-col" id="qvImgCol"></div>
    <div class="modal-info-col" id="qvInfoCol">
      <div class="loading" style="color:var(--muted)"><i class="fas fa-spinner fa-spin"></i> Đang tải…</div>
    </div>
  </div>
</div>

<script>
// Featured-products: yêu cầu đăng nhập, mở rent modal, rồi chuyển thanh toán
(function(){
  const isLoggedIn = <?= $isCustomerLoggedIn ? 'true' : 'false' ?>;
  const rModal = document.getElementById('rentModal');
  if(!rModal) return;

  const rClose = document.getElementById('rentModalClose');
  const rCancel = document.getElementById('rentModalCancel');
  const rForm = document.getElementById('rentModalForm');
  const rId = document.getElementById('rentModalId');
  const rName = document.getElementById('rentModalName');
  const rMeta = document.getElementById('rentModalMeta');
  const rMsg = document.getElementById('rentModalMsg');
  const rReceive = document.getElementById('rentReceive');
  const rReturn = document.getElementById('rentReturn');
  const rQty = document.getElementById('rentQty');
  const rSubmit = document.getElementById('rentModalSubmit');

  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const toStr = date => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  rReceive.min = toStr(today);
  rReturn.min = toStr(today);

  function closeRent(){
    rModal.classList.remove('open');
    rModal.setAttribute('aria-hidden','true');
    document.body.style.overflow = '';
  }

  function openRent(btn){
    rId.value = btn.dataset.id || '';
    rName.textContent = btn.dataset.name || 'Đặt thuê';
    rMeta.textContent = `Giá thuê: ${btn.dataset.price || '—'}₫ / lần · Đặt cọc: ${btn.dataset.deposit || '—'}₫`;
    rMsg.innerHTML = '';
    rQty.value = 1;

    if(!rReceive.value) rReceive.value = toStr(today);
    if(!rReturn.value){
      const nextDay = new Date(today);
      nextDay.setDate(nextDay.getDate() + 1);
      rReturn.value = toStr(nextDay);
    }

    rModal.classList.add('open');
    rModal.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';
  }

  document.querySelectorAll('.js-featured-rent').forEach(btn => {
    btn.addEventListener('click', event => {
      event.preventDefault();
      if(!isLoggedIn){
        window.location.href = `login.php?redirect=${encodeURIComponent(`index.php?rent=${encodeURIComponent(btn.dataset.id)}`)}`;
        return;
      }
      openRent(btn);
    });
  });

  rClose.addEventListener('click', closeRent);
  rCancel.addEventListener('click', closeRent);
  rModal.addEventListener('click', event => { if(event.target === rModal) closeRent(); });
  document.addEventListener('keydown', event => {
    if(event.key === 'Escape' && rModal.classList.contains('open')) closeRent();
  });
  rReceive.addEventListener('change', () => {
    if(rReceive.value){
      rReturn.min = rReceive.value;
      if(rReturn.value < rReceive.value) rReturn.value = rReceive.value;
    }
  });

  rForm.addEventListener('submit', event => {
    event.preventDefault();
    rMsg.style.color = '';
    rMsg.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
    rSubmit.disabled = true;

    const fd = new FormData(rForm);
    fd.append('action', 'add');
    fetch('ajax_cart.php', {
      method: 'POST',
      body: fd,
      headers: {'X-Requested-With':'XMLHttpRequest'}
    })
      .then(response => response.json())
      .then(data => {
        if(data && data.ok){
          window.location.href = 'checkout.php';
          return;
        }
        rMsg.style.color = 'var(--danger,#dc3545)';
        rMsg.textContent = (data && data.message) ? data.message : 'Không thể đặt thuê. Vui lòng thử lại.';
        rSubmit.disabled = false;
      })
      .catch(() => {
        rMsg.style.color = 'var(--danger,#dc3545)';
        rMsg.textContent = 'Lỗi kết nối. Vui lòng thử lại.';
        rSubmit.disabled = false;
      });
  });

  const rentId = new URLSearchParams(window.location.search).get('rent');
  if(isLoggedIn && rentId){
    const btn = Array.from(document.querySelectorAll('.js-featured-rent'))
      .find(item => item.dataset.id === rentId);
    history.replaceState({}, '', 'index.php');
    if(btn) openRent(btn);
  }
})();

// Quick-view modal
(function(){
  const modal    = document.getElementById('quickViewModal');
  const closeBtn = document.getElementById('quickViewClose');
  const imgCol   = document.getElementById('qvImgCol');
  const infoCol  = document.getElementById('qvInfoCol');

  function openModal(id){
    modal.classList.add('open');
    modal.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';
    imgCol.innerHTML  = '<div style="height:100%;display:grid;place-items:center;color:#ccc;font-size:3rem;"><i class="fas fa-spinner fa-spin"></i></div>';
    infoCol.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Đang tải…</div>';

    fetch('../client/ajax_product.php?id=' + encodeURIComponent(id))
      .then(r => r.json())
      .then(data => {
        if (!data || data.error) { infoCol.innerHTML = '<p style="padding:20px;color:var(--danger)">Không tải được thông tin.</p>'; return; }
        imgCol.innerHTML = data.image
          ? `<img src="${data.image}" alt="${data.name}" style="width:100%;height:100%;object-fit:cover;">`
          : `<div style="height:100%;display:grid;place-items:center;color:#ccc;font-size:4rem;"><i class="fas fa-shirt"></i></div>`;
        infoCol.innerHTML = `
          <div class="modal-category">${data.cat_name ?? ''}</div>
          <h2 class="modal-name">${data.name}</h2>
          <div class="modal-price">${data.price_fmt}<span class="modal-price-unit"> / lần thuê</span></div>
          <div class="modal-info-row"><i class="fas fa-ruler-horizontal"></i> Kích cỡ: <strong>${data.size}</strong></div>
          ${data.color ? `<div class="modal-info-row"><i class="fas fa-palette"></i> Màu sắc: <strong>${data.color}</strong></div>` : ''}
          <div class="modal-info-row"><i class="fas fa-coins"></i> Đặt cọc: <strong>${data.deposit_fmt}₫</strong></div>
          <div class="modal-desc">${data.description ?? ''}</div>
          <div class="modal-actions">
            <a href="product.php?id=${data.id}" class="btn-enquire" style="flex:1;min-height:46px;">
              <i class="fas fa-circle-info"></i> Xem chi tiết
            </a>
            <a href="product.php?id=${data.id}" class="btn-rent-now" style="flex:1;min-height:46px;">
              <i class="fas fa-calendar-check"></i> Đặt thuê
            </a>
          </div>`;
      })
      .catch(() => { infoCol.innerHTML = '<p style="padding:20px;color:var(--danger)">Lỗi kết nối.</p>'; });
  }

  function closeModal(){
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden','true');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.btn-quick-view').forEach(btn => {
    btn.addEventListener('click', () => openModal(btn.dataset.id));
  });
  closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
})();
</script>

<?php include 'footer.php'; ?>

<?php
/* ---- Helper: map Vietnamese colour names to hex for dot ---- */
function color_to_hex(string $name): string {
  $map = [
    'trắng'       => '#fff9f5',
    'trắng kem'   => '#fdf8ef',
    'đen'         => '#2a2a2a',
    'đỏ'          => '#c0392b',
    'xanh navy'   => '#1e3a5f',
    'xanh'        => '#2980b9',
    'vàng'        => '#f1c40f',
    'hồng'        => '#e91e8c',
    'tím'         => '#8e44ad',
    'xám'         => '#7f8c8d',
    'nâu'         => '#7d4e30',
    'kem'         => '#f5deb3',
    'bạc'         => '#bdc3c7',
  ];
  $key = mb_strtolower(trim($name));
  foreach ($map as $k => $v) {
    if (str_contains($key, $k)) return $v;
  }
  return '#c5c5d2';
}
?>

