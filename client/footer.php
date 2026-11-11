<?php
/* client/footer.php – shared footer for all customer-facing pages */
?>

<!-- ========== SITE FOOTER ========== -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-top">

      <!-- Brand col -->
      <div class="footer-brand">
        <div class="site-logo">
          <div class="site-logo-text">
            Lumi Dress
            <span>Cho thuê trang phục</span>
          </div>
        </div>
        <p class="footer-tagline">
          Chúng tôi mang đến vẻ đẹp sang trọng cho mọi khoảnh khắc đặc biệt trong cuộc đời bạn — từ lễ cưới, tiệc dạ hội đến sự kiện quan trọng.
        </p>
        <div class="footer-social">
          <a href="#" class="social-btn" title="Facebook" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="#" class="social-btn" title="Instagram" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="#" class="social-btn" title="TikTok" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
          <a href="#" class="social-btn" title="YouTube" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
        </div>
      </div>

      <!-- Quick links -->
      <div class="footer-col">
        <h4>Khám phá</h4>
        <ul class="footer-links">
          <li><a href="index.php"><i class="fas fa-chevron-right"></i> Trang chủ</a></li>
          <li><a href="products.php"><i class="fas fa-chevron-right"></i> Toàn bộ trang phục</a></li>
          <li><a href="products.php?cat=1"><i class="fas fa-chevron-right"></i> Váy cưới</a></li>
          <li><a href="products.php?cat=2"><i class="fas fa-chevron-right"></i> Vest nam</a></li>
          <li><a href="products.php?cat=3"><i class="fas fa-chevron-right"></i> Áo dài</a></li>
          <li><a href="products.php?cat=4"><i class="fas fa-chevron-right"></i> Váy dạ hội</a></li>
        </ul>
      </div>

      <!-- About links -->
      <div class="footer-col">
        <h4>Hỗ trợ</h4>
        <ul class="footer-links">
          <li><a href="about.php"><i class="fas fa-chevron-right"></i> Về Lumi Dress</a></li>
          <li><a href="contact.php"><i class="fas fa-chevron-right"></i> Liên hệ &amp; Đặt lịch</a></li>
          <li><a href="policy.php"><i class="fas fa-chevron-right"></i> Chính sách thuê</a></li>
          <li><a href="policy.php#deposit"><i class="fas fa-chevron-right"></i> Chính sách đặt cọc</a></li>
          <li><a href="policy.php#return"><i class="fas fa-chevron-right"></i> Quy định trả đồ</a></li>
        </ul>
      </div>

      <!-- Contact -->
      <div class="footer-col">
        <h4>Liên hệ</h4>
        <div class="footer-contact-item">
          <i class="fas fa-map-marker-alt"></i>
          <span>12 Chùa Bộc, Kim Liên, Hà Nội</span>
        </div>
        <div class="footer-contact-item">
          <i class="fas fa-phone"></i>
          <a href="tel:0987654321">0987 654 321</a>
        </div>
        <div class="footer-contact-item">
          <i class="fas fa-envelope"></i>
          <a href="mailto:hello@lumidress.vn">hello@lumidress.vn</a>
        </div>
        <div class="footer-contact-item">
          <i class="fas fa-clock"></i>
          <span>Thứ 2 – Chủ nhật: 8:00 – 20:00</span>
        </div>
      </div>

    </div><!-- /footer-top -->

    <div class="footer-bottom">
      <p class="footer-copy">
        &copy; <?= date('Y') ?> <a href="index.php">Lumi Dress</a>. Mọi quyền được bảo lưu.
      </p>
      <div class="footer-bottom-links">
        <a href="policy.php">Chính sách &amp; Điều khoản</a>
        <a href="policy.php#privacy">Bảo mật</a>
        <a href="contact.php">Liên hệ</a>
      </div>
    </div>

  </div><!-- /container -->
</footer>

<!-- ========== SCROLL TO TOP ========== -->
<button class="scroll-top" id="scrollTopBtn" aria-label="Lên đầu trang">
  <i class="fas fa-chevron-up"></i>
</button>

<!-- ========== TOAST CONTAINER ========== -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- ========== SHARED JS ========== -->
<script>
// ----- Scroll-to-top button -----
const scrollBtn = document.getElementById('scrollTopBtn');
window.addEventListener('scroll', () => {
  if (scrollBtn) scrollBtn.classList.toggle('visible', window.scrollY > 320);
});
if (scrollBtn) scrollBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

// ----- Header scroll shadow -----
const hdr = document.getElementById('siteHeader');
window.addEventListener('scroll', () => {
  if (hdr) hdr.style.boxShadow = window.scrollY > 10
    ? '0 4px 24px rgba(50,45,90,.13)'
    : '0 4px 14px rgba(50,45,90,.07)';
}, { passive: true });

// ----- Toast helper (globally available) -----
window.showToast = function(msg, type = '') {
  const wrap  = document.getElementById('toastWrap');
  if (!wrap) return;
  const toast = document.createElement('div');
  toast.className = 'toast ' + type;
  const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-circle-xmark' : 'fa-circle-info';
  toast.innerHTML = `<i class="fas ${icon}"></i> ${msg}`;
  wrap.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(16px)'; toast.style.transition = '.3s'; }, 2800);
  setTimeout(() => toast.remove(), 3200);
};
</script>
</body>
</html>

