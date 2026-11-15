<?php
require_once '../config.php';
require_once '../customer_auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$pageTitle = 'Giỏ thuê – Lumi Dress';
include 'header.php';
$cart = $_SESSION['cart'] ?? [];
$items=[];$rent=0;$deposit=0;
foreach($cart as $key=>$line){
  $id=(int)($line['id']??0); $qty=max(1,(int)($line['qty']??1));
  $receive=$line['receive']??''; $ret=$line['return']??'';
  if($id<=0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$receive) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$ret)) continue;
  $stmt=$conn->prepare("SELECT c.id,c.name,c.rental_price,c.deposit,c.image,cat.name AS cat_name FROM costumes c LEFT JOIN categories cat ON cat.id=c.category_id WHERE c.id=?");
  $stmt->bind_param('i',$id);$stmt->execute();$p=$stmt->get_result()->fetch_assoc(); if(!$p) continue;
  $days=rental_days_count($receive,$ret);
  $lineRent=(float)$p['rental_price']*$qty*$days; $lineDep=(float)$p['deposit']*$qty;
  $rent+=$lineRent; $deposit+=$lineDep;
  $items[]=['key'=>$key,'p'=>$p,'qty'=>$qty,'receive'=>$receive,'return'=>$ret,'days'=>$days,'lineRent'=>$lineRent,'lineDep'=>$lineDep];
}
?>
<section class="section" style="background:linear-gradient(180deg,#faf9ff 0%,#ffffff 60%);">
  <div class="container">
    <div class="section-head" style="margin-bottom:28px;">
      <span class="section-eyebrow">Đặt thuê</span>
      <h2 class="section-title" style="margin-bottom:6px;">Giỏ thuê trang phục</h2>
      <p style="color:var(--muted);margin:0;">Kiểm tra và điều chỉnh thời gian, số lượng trước khi xác nhận đặt cọc.</p>
    </div>

    <?php if(empty($items)): ?>
      <div class="panel" style="padding:48px 24px;text-align:center;border-radius:18px;">
        <div style="font-size:42px;color:#c7c5da;margin-bottom:12px;"><i class="fas fa-bag-shopping"></i></div>
        <h3 style="margin:0 0 8px;">Giỏ thuê đang trống</h3>
        <p style="color:var(--muted);margin:0 0 18px;">Hãy chọn trang phục bạn yêu thích để bắt đầu đặt thuê.</p>
        <a href="products.php" class="btn-rent-now" style="display:inline-flex;align-items:center;gap:8px;text-decoration:none;"><i class="fas fa-shirt"></i> Xem sản phẩm</a>
      </div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:24px;align-items:start;">
        <div style="display:flex;flex-direction:column;gap:16px;">
          <?php foreach($items as $it): $p=$it['p']; $img=$p['image']?('../uploads/'.rawurlencode($p['image'])):''; ?>
          <article class="panel cart-item" data-key="<?= htmlspecialchars($it['key']) ?>" style="padding:18px;border-radius:16px;border:1px solid #eee;background:#fff;">
            <div style="display:grid;grid-template-columns:110px 1fr;gap:16px;align-items:start;">
              <a href="product.php?id=<?= (int)$p['id'] ?>" style="display:block;width:110px;height:140px;border-radius:12px;overflow:hidden;background:#f4f3f8;">
                <?php if($img): ?>
                  <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['name']) ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                  <div style="width:100%;height:100%;display:grid;place-items:center;color:#c7c5da;font-size:28px;"><i class="fas fa-shirt"></i></div>
                <?php endif; ?>
              </a>
              <div style="min-width:0;">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:10px;">
                  <div>
                    <div style="font-size:12px;color:var(--primary);font-weight:600;text-transform:uppercase;letter-spacing:.4px;"><?= htmlspecialchars($p['cat_name']??'Trang phục') ?></div>
                    <h3 style="margin:4px 0 4px;font-size:1.05rem;"><a href="product.php?id=<?= (int)$p['id'] ?>" style="color:var(--ink);text-decoration:none;"><?= htmlspecialchars($p['name']) ?></a></h3>
                    <div style="font-size:13px;color:var(--muted);">Giá thuê: <strong style="color:var(--ink);"><?= format_price($p['rental_price']) ?>đ</strong> / lần · Cọc: <strong style="color:var(--ink);"><?= format_price($p['deposit']) ?>đ</strong></div>
                  </div>
                  <button type="button" class="icon-btn danger" title="Xóa khỏi giỏ" data-remove-key="<?= htmlspecialchars($it['key']) ?>" style="flex:0 0 auto;"><i class="fas fa-trash"></i></button>
                </div>

                <form class="cart-update-form" data-key="<?= htmlspecialchars($it['key']) ?>" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;align-items:end;">
                  <input type="hidden" name="key" value="<?= htmlspecialchars($it['key']) ?>">
                  <label style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:#666;">
                    <span>Ngày nhận</span>
                    <input type="date" name="receive" value="<?= htmlspecialchars($it['receive']) ?>" required style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                  </label>
                  <label style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:#666;">
                    <span>Ngày trả</span>
                    <input type="date" name="return" value="<?= htmlspecialchars($it['return']) ?>" required style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                  </label>
                  <label style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:#666;">
                    <span>Số lượng</span>
                    <input type="number" name="qty" value="<?= (int)$it['qty'] ?>" min="1" style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;">
                  </label>
                  <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn-enquire" style="min-height:42px;flex:1;justify-content:center;"><i class="fas fa-save"></i> Lưu</button>
                  </div>
                </form>

                <div class="cart-item-msg" style="min-height:20px;margin-top:8px;font-size:13px;"></div>

                <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:8px;padding-top:12px;border-top:1px dashed #ececf3;">
                  <div style="font-size:13px;color:#666;">Thời gian thuê: <strong style="color:var(--ink);"><?= $it['days'] ?> ngày</strong></div>
                  <div style="font-size:13px;color:#666;">Tiền thuê: <strong style="color:var(--primary);"><?= format_price($it['lineRent']) ?>đ</strong></div>
                  <div style="font-size:13px;color:#666;">Tiền cọc: <strong style="color:var(--ink);"><?= format_price($it['lineDep']) ?>đ</strong></div>
                </div>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
          <div style="font-size:13px;color:var(--muted);"><i class="fas fa-circle-info"></i> Thay đổi ngày nhận/trả hoặc số lượng rồi bấm <strong>Lưu</strong> để cập nhật giá và khả dụng.</div>
        </div>

        <aside class="panel" style="padding:22px;border-radius:16px;border:1px solid #eee;position:sticky;top:100px;background:#fff;">
          <h3 style="margin:0 0 16px;font-size:1.1rem;">Tóm tắt đơn thuê</h3>
          <div style="display:flex;justify-content:space-between;margin:10px 0;color:#555;"><span>Số mặt hàng</span><strong><?= count($items) ?></strong></div>
          <div style="display:flex;justify-content:space-between;margin:10px 0;color:#555;"><span>Tiền thuê dự kiến</span><strong><?= format_price($rent) ?>đ</strong></div>
          <div style="display:flex;justify-content:space-between;margin:10px 0;color:#555;"><span>Tiền cọc cần thanh toán</span><strong><?= format_price($deposit) ?>đ</strong></div>
          <div style="display:flex;justify-content:space-between;margin:16px 0 0;padding-top:14px;border-top:1px solid #eee;font-size:18px;"><span>Tổng tạm tính</span><strong style="color:var(--primary)"><?= format_price($rent+$deposit) ?>đ</strong></div>
          <a href="checkout.php" class="btn-rent-now" style="display:inline-flex;width:100%;justify-content:center;margin-top:18px;text-decoration:none;min-height:48px;"><i class="fas fa-credit-card"></i> Xác nhận & đặt cọc</a>
          <p style="margin:12px 0 0;font-size:13px;color:#777;line-height:1.5;">Bạn sẽ được chuyển tới trang chi tiết đơn thuê để xem mã QR và thanh toán cọc.</p>
          <a href="products.php" style="display:block;text-align:center;margin-top:12px;font-size:13px;color:var(--primary);text-decoration:none;">&larr; Tiếp tục chọn trang phục</a>
        </aside>
      </div>
      <style>
        @media (max-width: 900px){
          .container > div[style*="grid-template-columns:minmax(0,1fr) 360px"]{grid-template-columns:1fr !important;}
          .container > div[style*="grid-template-columns:minmax(0,1fr) 360px"] aside{position:static !important;}
        }
        @media (max-width: 640px){
          .cart-item > div{grid-template-columns:1fr !important;}
          .cart-item a[href^="product.php"]{width:100% !important;height:220px !important;}
          .cart-update-form{grid-template-columns:1fr 1fr !important;}
        }
      </style>
    <?php endif; ?>
  </div>
</section>
<script>
(function(){
  function postCart(fd){ return fetch('ajax_cart.php',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()); }
  document.querySelectorAll('[data-remove-key]').forEach(btn=>{
    btn.addEventListener('click', async function(){
      if(!confirm('Xóa sản phẩm này khỏi giỏ thuê?')) return;
      const fd=new FormData(); fd.append('action','remove'); fd.append('key', this.dataset.removeKey);
      await postCart(fd); location.reload();
    });
  });
  document.querySelectorAll('.cart-update-form').forEach(form=>{
    const recv=form.querySelector('input[name="receive"]');
    const ret=form.querySelector('input[name="return"]');
    if(recv && ret){
      recv.addEventListener('change',()=>{ if(recv.value){ ret.min=recv.value; if(ret.value < recv.value) ret.value=recv.value; } });
      if(recv.value) ret.min=recv.value;
    }
    form.addEventListener('submit', async function(e){
      e.preventDefault();
      const card=form.closest('.cart-item');
      const msg=card ? card.querySelector('.cart-item-msg') : null;
      if(msg){ msg.style.color=''; msg.innerHTML='<i class="fas fa-spinner fa-spin"></i> Đang cập nhật...'; }
      const fd=new FormData(form); fd.append('action','update');
      try{
        const d=await postCart(fd);
        if(d && d.ok){ location.reload(); return; }
        if(msg){ msg.style.color='var(--danger,#dc3545)'; msg.textContent=(d && d.message)?d.message:'Không thể cập nhật. Vui lòng thử lại.'; }
      }catch(err){ if(msg){ msg.style.color='var(--danger,#dc3545)'; msg.textContent='Lỗi kết nối. Vui lòng thử lại.'; } }
    });
  });
})();
</script>
<?php include 'footer.php'; ?>
