/* assets/js/product-detail.js – logic trang chi tiết sản phẩm
 * Cần window.PD_CONFIG (do product.php in ra) và window.showToast (có sẵn trong footer.php).
 * Không sửa header.php: huy hiệu số lượng được JS chèn vào nút "Giỏ thuê" có sẵn. */
(function () {
  'use strict';

  var cfg = window.PD_CONFIG;
  if (!cfg) return;

  function $(id) { return document.getElementById(id); }
  var receive = $('pdReceive'), ret = $('pdReturn'), qtyInput = $('pdQty');
  var minus = $('pdMinus'), plus = $('pdPlus'), addBtn = $('pdAdd'), buyBtn = $('pdBuy');
  var elDays = $('pdDays'), elRent = $('pdRentTotal'), elDeposit = $('pdDepositTotal'), elGrand = $('pdGrand');

  function toast(msg, type) {
    if (window.showToast) window.showToast(msg, type);
    else alert(msg);
  }
  function money(n) { return Math.round(n).toLocaleString('vi-VN') + '₫'; }
  function pad(n) { return String(n).padStart(2, '0'); }
  function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function parseYmd(s) {
    var p = (s || '').split('-').map(Number);
    return p.length === 3 && p[0] ? new Date(p[0], p[1] - 1, p[2]) : null;
  }

  /* ---------- Huy hiệu giỏ trên header (chèn bằng JS) ---------- */
  var badges = [];
  function initBadges() {
    var links = document.querySelectorAll('.btn-header-cta[href="cart.php"], .mobile-nav-link[href="cart.php"]');
    links.forEach(function (a) {
      var b = document.createElement('span');
      b.className = 'cart-badge';
      a.appendChild(b);
      badges.push(b);
    });
    updateBadges(cfg.cartCount || 0);
  }
  function updateBadges(n) {
    badges.forEach(function (b) {
      b.textContent = n;
      b.hidden = !(n > 0);
    });
  }
  initBadges();

  /* ---------- Số lượng ---------- */
  function clampQty() {
    var v = parseInt(qtyInput.value, 10);
    if (isNaN(v) || v < 1) v = 1;
    if (v > cfg.maxQty) v = cfg.maxQty;
    qtyInput.value = v;
    return v;
  }

  /* ---------- Số ngày & tiền ---------- */
  function calcDays() {
    var a = parseYmd(receive.value), b = parseYmd(ret.value);
    if (!a || !b || b < a) return 0;
    return Math.round((b - a) / 86400000) + 1; // tính cả ngày nhận và ngày trả
  }

  function refresh() {
    var qty = clampQty();
    var days = calcDays();
    if (days < 1) {
      elDays.textContent = elRent.textContent = elDeposit.textContent = elGrand.textContent = '—';
      return;
    }
    var rent = cfg.price * qty * days;
    var dep = cfg.deposit * qty;
    elDays.textContent = days + ' ngày';
    elRent.textContent = money(rent);
    elDeposit.textContent = money(dep);
    elGrand.textContent = money(rent + dep);
  }

  if (cfg.canRent) {
    // Ngày mặc định: nhận ngày mai, trả ngày kế tiếp
    var t = new Date(); t.setHours(0, 0, 0, 0);
    var d1 = new Date(t); d1.setDate(d1.getDate() + 1);
    var d2 = new Date(t); d2.setDate(d2.getDate() + 2);
    receive.value = ymd(d1);
    ret.value = ymd(d2);
    ret.min = receive.value;

    receive.addEventListener('change', function () {
      if (!receive.value) return;
      ret.min = receive.value;
      if (!ret.value || ret.value < receive.value) ret.value = receive.value;
      refresh();
    });
    ret.addEventListener('change', refresh);

    minus.addEventListener('click', function () { qtyInput.value = clampQty() - 1; refresh(); });
    plus.addEventListener('click', function () {
      var v = clampQty();
      if (v >= cfg.maxQty) toast('Chỉ còn ' + cfg.maxQty + ' bộ trong kho.', 'error');
      qtyInput.value = v + 1;
      refresh();
    });
    qtyInput.addEventListener('input', refresh);
    qtyInput.addEventListener('blur', refresh);

    refresh();
  }

  function setLoading(btn, on) {
    if (!btn) return;
    if (on) {
      btn.dataset.html = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý…';
    } else {
      btn.disabled = false;
      if (btn.dataset.html) btn.innerHTML = btn.dataset.html;
    }
  }

  /* ---------- Thêm vào giỏ / Thuê ngay ---------- */
  function addToCart(btn, goCheckout) {
    if (!cfg.canRent) return;

    if (calcDays() < 1) {
      toast('Vui lòng chọn ngày nhận và ngày trả hợp lệ.', 'error');
      return;
    }

    var fd = new FormData();
    fd.append('action', 'add');
    fd.append('id', cfg.id);
    fd.append('qty', clampQty());
    fd.append('receive_date', receive.value);
    fd.append('return_date', ret.value);

    setLoading(btn, true);
    fetch(cfg.ajaxCart, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          setLoading(btn, false);
          toast(data.message || 'Không thể thêm vào giỏ.', 'error');
          return;
        }
        updateBadges(data.cart_count);
        if (goCheckout) {
          window.location.href = cfg.checkoutUrl;
          return;
        }
        setLoading(btn, false);
        toast(data.message, 'success');
      })
      .catch(function () {
        setLoading(btn, false);
        toast('Lỗi kết nối, vui lòng thử lại.', 'error');
      });
  }

  if (addBtn) addBtn.addEventListener('click', function () { addToCart(addBtn, false); });
  if (buyBtn) buyBtn.addEventListener('click', function () { addToCart(buyBtn, true); });
})();