<?php
require_once '../config.php';

// --- Filters ---
$search  = trim($_GET['q']   ?? '');
$catId   = (int)($_GET['cat'] ?? 0);
$sort    = $_GET['sort']  ?? 'newest';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$receive = trim($_GET['receive'] ?? '');
$returnD = trim($_GET['return'] ?? '');
$hasRange = preg_match('/^\d{4}-\d{2}-\d{2}$/',$receive) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$returnD) && strtotime($returnD) >= strtotime($receive);

// --- Categories for filter ---
$cats = $conn->query("SELECT * FROM categories ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// --- Build query ---
$where  = ["c.status = 'available'"];
$params = [];
$types  = '';

if ($search !== '') {
  $where[]  = "(c.name LIKE ? OR c.code LIKE ? OR c.color LIKE ?)";
  $like     = "%$search%";
  $params[] = $like; $params[] = $like; $params[] = $like;
  $types   .= 'sss';
}
if ($catId > 0) {
  $where[]  = "c.category_id = ?";
  $params[] = $catId;
  $types   .= 'i';
}

$orderMap = [
  'newest'    => 'c.id DESC',
  'price_asc' => 'c.rental_price ASC',
  'price_desc'=> 'c.rental_price DESC',
  'name_asc'  => 'c.name ASC',
];
$orderBy = $orderMap[$sort] ?? 'c.id DESC';

$whereSQL = implode(' AND ', $where);

// --- Fetch products (lọc theo lịch trống nếu chọn ngày) ---
$sql  = "SELECT c.*, cat.name AS cat_name FROM costumes c
         LEFT JOIN categories cat ON cat.id = c.category_id
         WHERE $whereSQL ORDER BY $orderBy";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$allProducts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if ($hasRange) {
  $filtered = [];
  foreach ($allProducts as $p) {
    $avail = function_exists('available_quantity') ? available_quantity($conn, (int)$p['id'], $receive, $returnD, 0) : (int)$p['quantity'];
    $p['avail_in_range'] = $avail;
    if ($avail > 0) $filtered[] = $p;
  }
  $allProducts = $filtered;
} else {
  foreach ($allProducts as &$p) { $p['avail_in_range'] = (int)$p['quantity']; }
  unset($p);
}
$total = count($allProducts);
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$products = array_slice($allProducts, $offset, $perPage);

// --- Page label ---
$pageTitle = 'Trang phục cho thuê' . ($catId > 0 ? ' – ' . ($cats[array_search($catId, array_column($cats,'id'))]['name'] ?? '') : '') . ' – Lumi Dress';

function color_to_hex(string $name): string {
  $map = ['trắng'=>'#fff9f5','trắng kem'=>'#fdf8ef','đen'=>'#2a2a2a','đỏ'=>'#c0392b','xanh navy'=>'#1e3a5f','xanh'=>'#2980b9','vàng'=>'#f1c40f','hồng'=>'#e91e8c','tím'=>'#8e44ad','xám'=>'#7f8c8d','nâu'=>'#7d4e30','kem'=>'#f5deb3','bạc'=>'#bdc3c7'];
  $key = mb_strtolower(trim($name));
  foreach ($map as $k => $v) { if (str_contains($key, $k)) return $v; }
  return '#c5c5d2';
}

include 'header.php';
?>

<!-- Page hero (compact) -->
<div style="background:linear-gradient(130deg,#2d2655,#4f459e);padding:48px 0 36px;color:#fff;">
  <div class="container">
    <div style="max-width:600px;">
      <span style="font-size:.72rem;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.6);">Danh sách</span>
      <h1 style="font-family:'Playfair Display',serif;font-size:clamp(1.6rem,3vw,2.4rem);color:#fff;margin:8px 0 10px;">Trang phục cho thuê</h1>
      <p style="color:rgba(255,255,255,.7);margin:0;font-size:.93rem;">Khám phá <?= $total ?> bộ trang phục phù hợp<?= $hasRange ? ' trong khung ngày '.date('d/m/Y',strtotime($receive)).' - '.date('d/m/Y',strtotime($returnD)) : '' ?>.</p>
    </div>
  </div>
</div>

<!-- Search & filter bar -->
<div class="search-section">
  <div class="container">
    <form method="GET" action="products.php">
      <div class="search-row">
        <div class="search-field">
          <i class="fas fa-search" aria-hidden="true"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                 placeholder="Tìm trang phục, màu sắc, mã sản phẩm…"
                 aria-label="Tìm kiếm trang phục">
        </div>
        <select name="cat" class="filter-select" aria-label="Lọc theo danh mục">
          <option value="0">Tất cả danh mục</option>
          <?php foreach ($cats as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $catId===$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <!--<input type="date" name="receive" class="filter-select" value="<?= htmlspecialchars($receive) ?>" aria-label="Ngày nhận" title="Ngày nhận">
        <input type="date" name="return" class="filter-select" value="<?= htmlspecialchars($returnD) ?>" aria-label="Ngày trả" title="Ngày trả">
        -->
        <select name="sort" class="filter-select" aria-label="Sắp xếp">
          <option value="newest"     <?= $sort==='newest'     ?'selected':'' ?>>Mới nhất</option>
          <option value="price_asc"  <?= $sort==='price_asc'  ?'selected':'' ?>>Giá tăng dần</option>
          <option value="price_desc" <?= $sort==='price_desc' ?'selected':'' ?>>Giá giảm dần</option>
          <option value="name_asc"   <?= $sort==='name_asc'   ?'selected':'' ?>>Tên A–Z</option>
        </select>
        <button type="submit" class="btn-search">
          <i class="fas fa-filter" aria-hidden="true"></i> Lọc
        </button>
        <?php if ($search||$catId||$hasRange): ?>
        <a href="products.php" style="color:var(--muted);font-size:.85rem;white-space:nowrap;text-decoration:none;" aria-label="Xóa bộ lọc">
          <i class="fas fa-xmark" aria-hidden="true"></i> Xóa lọc
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Category chips -->
<section class="section-sm" style="padding-bottom:0;">
  <div class="container">
    <div class="category-filter" role="list">
      <a href="products.php<?= $sort!=='newest'?'?sort='.$sort:'' ?>" class="cat-chip <?= $catId===0?'active':'' ?>" role="listitem">
        <i class="fas fa-th-large"></i> Tất cả
      </a>
      <?php foreach ($cats as $c):
        $icons = [1=>'fa-champagne-glasses',2=>'fa-user-tie',3=>'fa-person-dress',4=>'fa-star'];
        $icon  = $icons[$c['id']] ?? 'fa-shirt';
      ?>
      <a href="products.php?cat=<?= $c['id'] ?>&sort=<?= htmlspecialchars($sort) ?>"
         class="cat-chip <?= $catId===$c['id']?'active':'' ?>" role="listitem">
        <i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($c['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Product listing -->
<section class="section" style="padding-top:32px;">
  <div class="container">

    <?php if (empty($products)): ?>
    <div class="empty-state">
      <i class="fas fa-shirt"></i>
      <h3>Không tìm thấy trang phục nào</h3>
      <p>Vui lòng thử từ khóa khác hoặc <a href="products.php" style="color:var(--primary)">xem tất cả</a>.</p>
    </div>

    <?php else: ?>
    <!-- Result count -->
    <p style="color:var(--muted);font-size:.88rem;margin-bottom:22px;">
      Hiển thị <strong style="color:var(--ink);"><?= count($products) ?></strong> / <?= $total ?> trang phục
      <?php if ($search): ?> cho "<strong style="color:var(--ink);"><?= htmlspecialchars($search) ?></strong>"<?php endif; ?>
    </p>

    <div class="product-grid" role="list">
      <?php foreach ($products as $item):
        $imgSrc = $item['image'] && file_exists('../uploads/'.$item['image'])
                  ? '../uploads/'.htmlspecialchars($item['image']) : null;
        $isNew  = strtotime($item['created_at']) > strtotime('-30 days');
      ?>
      <article class="product-card" role="listitem">
        <div class="product-img-wrap">
          <?php if ($imgSrc): ?>
            <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
          <?php else: ?>
            <div style="width:100%;height:100%;display:grid;place-items:center;background:#f4f3f8;color:#ccc;font-size:3rem;" aria-hidden="true"><i class="fas fa-shirt"></i></div>
          <?php endif; ?>
          <div class="product-badges">
            <?php if ($isNew): ?><span class="product-badge badge-new"><i class="fas fa-sparkles"></i> Mới</span><?php endif; ?>
          </div>
          <div class="product-overlay" aria-hidden="true">
            <button class="btn-quick-view" data-id="<?= $item['id'] ?>" aria-label="Xem nhanh <?= htmlspecialchars($item['name']) ?>">
              <i class="fas fa-eye"></i> Xem nhanh
            </button>
          </div>
        </div>
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
          <div class="price-deposit">Đặt cọc: <strong><?= format_price($item['deposit']) ?>₫</strong> <?= $hasRange ? '<span style="margin-left:8px;color:#2e7d32;font-size:12px;">· Còn '.(int)($item['avail_in_range']??$item['quantity']).' bộ</span>' : '' ?></div>
        </div>
        <div class="product-footer">
          <a href="product.php?id=<?= $item['id'] ?>" class="btn-enquire"><i class="fas fa-circle-info"></i> Chi tiết</a>
          <a href="#" class="btn-rent-now js-open-rent" data-id="<?= $item['id'] ?>" data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>" data-price="<?= format_price($item['rental_price']) ?>" data-deposit="<?= format_price($item['deposit']) ?>"><i class="fas fa-calendar-check"></i> Đặt thuê</a>
        </div>
      </article>
      <?php endforeach; ?>
    </div><!-- /product-grid -->

    <!-- Pagination -->
    <?php if ($totalPages > 1):
      $qs = http_build_query(array_filter(['q'=>$search,'cat'=>$catId?:null,'sort'=>$sort!=='newest'?$sort:null,'receive'=>$hasRange?$receive:null,'return'=>$hasRange?$returnD:null]));
      $qs = $qs ? '&'.$qs : '';
    ?>
    <nav class="pagination" aria-label="Phân trang">
      <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?><?= $qs ?>" class="page-btn" aria-label="Trang trước"><i class="fas fa-chevron-left"></i></a>
      <?php else: ?>
        <span class="page-btn disabled" aria-disabled="true"><i class="fas fa-chevron-left"></i></span>
      <?php endif; ?>

      <?php
      $start = max(1, $page-2);
      $end   = min($totalPages, $page+2);
      if ($start > 1) echo '<span class="page-btn disabled" aria-hidden="true">…</span>';
      for ($i=$start; $i<=$end; $i++):
      ?>
        <a href="?page=<?= $i ?><?= $qs ?>" class="page-btn <?= $i===$page?'active':'' ?>" aria-label="Trang <?= $i ?>" <?= $i===$page?'aria-current="page"':'' ?>><?= $i ?></a>
      <?php endfor;
      if ($end < $totalPages) echo '<span class="page-btn disabled" aria-hidden="true">…</span>';
      ?>

      <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page+1 ?><?= $qs ?>" class="page-btn" aria-label="Trang sau"><i class="fas fa-chevron-right"></i></a>
      <?php else: ?>
        <span class="page-btn disabled" aria-disabled="true"><i class="fas fa-chevron-right"></i></span>
      <?php endif; ?>
    </nav>
    <?php endif; ?>

    <?php endif; ?>

  </div><!-- /container -->
</section>

<!-- Rent modal -->
<div class="modal-backdrop-custom" id="rentModal" role="dialog" aria-modal="true" aria-label="Đặt thuê trang phục" aria-hidden="true">
  <div class="modal-box" style="max-width:520px;grid-template-columns:1fr;">
    <button class="modal-close-btn" id="rentModalClose" aria-label="Đóng"><i class="fas fa-times"></i></button>
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

<!-- Quick-view modal -->
<div class="modal-backdrop-custom" id="quickViewModal" role="dialog" aria-modal="true" aria-label="Xem nhanh trang phục" aria-hidden="true">
  <div class="modal-box">
    <button class="modal-close-btn" id="quickViewClose" aria-label="Đóng"><i class="fas fa-times"></i></button>
    <div class="modal-img-col" id="qvImgCol"></div>
    <div class="modal-info-col" id="qvInfoCol"><div class="loading"><i class="fas fa-spinner fa-spin"></i> Đang tải…</div></div>
  </div>
</div>
<script>
(function(){
  const modal=document.getElementById('quickViewModal'),closeBtn=document.getElementById('quickViewClose'),imgCol=document.getElementById('qvImgCol'),infoCol=document.getElementById('qvInfoCol');
  function open(id){modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';imgCol.innerHTML='<div style="height:100%;display:grid;place-items:center;color:#ccc;font-size:3rem;"><i class="fas fa-spinner fa-spin"></i></div>';infoCol.innerHTML='<div class="loading"><i class="fas fa-spinner fa-spin"></i> Đang tải…</div>';fetch('../client/ajax_product.php?id='+encodeURIComponent(id)).then(r=>r.json()).then(d=>{if(!d||d.error){infoCol.innerHTML='<p style="padding:20px;color:var(--danger)">Không tải được thông tin.</p>';return;}imgCol.innerHTML=d.image?`<img src="${d.image}" alt="${d.name}" style="width:100%;height:100%;object-fit:cover;">`:'<div style="height:100%;display:grid;place-items:center;color:#ccc;font-size:4rem;"><i class="fas fa-shirt"></i></div>';infoCol.innerHTML=`<div class="modal-category">${d.cat_name??''}</div><h2 class="modal-name">${d.name}</h2><div class="modal-price">${d.price_fmt}<span class="modal-price-unit"> / lần thuê</span></div><div class="modal-info-row"><i class="fas fa-ruler-horizontal"></i> Kích cỡ: <strong>${d.size}</strong></div>${d.color?`<div class="modal-info-row"><i class="fas fa-palette"></i> Màu sắc: <strong>${d.color}</strong></div>`:''}<div class="modal-info-row"><i class="fas fa-coins"></i> Đặt cọc: <strong>${d.deposit_fmt}₫</strong></div><div class="modal-desc">${d.description??''}</div><div class="modal-actions"><a href="product.php?id=${d.id}" class="btn-enquire" style="flex:1;min-height:46px;"><i class="fas fa-circle-info"></i> Xem chi tiết</a><a href="product.php?id=${d.id}" class="btn-rent-now" style="flex:1;min-height:46px;"><i class="fas fa-calendar-check"></i> Đặt thuê</a></div>`;}).catch(()=>{infoCol.innerHTML='<p style="padding:20px;color:var(--danger)">Lỗi kết nối.</p>';});}
  function close(){modal.classList.remove('open');modal.setAttribute('aria-hidden','true');document.body.style.overflow='';}
  document.querySelectorAll('.btn-quick-view').forEach(b=>b.addEventListener('click',()=>open(b.dataset.id)));
  closeBtn.addEventListener('click',close);
  modal.addEventListener('click',e=>{if(e.target===modal)close();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
})();

// Rent modal
(function(){
  const rModal=document.getElementById('rentModal');
  if(!rModal) return;
  const rClose=document.getElementById('rentModalClose');
  const rCancel=document.getElementById('rentModalCancel');
  const rForm=document.getElementById('rentModalForm');
  const rId=document.getElementById('rentModalId');
  const rName=document.getElementById('rentModalName');
  const rMeta=document.getElementById('rentModalMeta');
  const rMsg=document.getElementById('rentModalMsg');
  const rReceive=document.getElementById('rentReceive');
  const rReturn=document.getElementById('rentReturn');
  const rQty=document.getElementById('rentQty');
  const rSubmit=document.getElementById('rentModalSubmit');
  const today=new Date(); today.setHours(0,0,0,0);
  const toStr=d=>d.toISOString().slice(0,10);
  rReceive.min=toStr(today);
  rReturn.min=toStr(today);
  function openRent(btn){
    rId.value=btn.dataset.id||'';
    rName.textContent=btn.dataset.name||'Đặt thuê';
    rMeta.textContent=`Giá thuê: ${btn.dataset.price||'—'}₫ / lần · Đặt cọc: ${btn.dataset.deposit||'—'}₫`;
    rMsg.innerHTML='';
    rQty.value=1;
    if(!rReceive.value) rReceive.value=toStr(today);
    if(!rReturn.value){ const t=new Date(today); t.setDate(t.getDate()+1); rReturn.value=toStr(t); }
    rModal.classList.add('open'); rModal.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden';
  }
  function closeRent(){ rModal.classList.remove('open'); rModal.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }
  document.querySelectorAll('.js-open-rent').forEach(b=>b.addEventListener('click',e=>{ e.preventDefault(); openRent(b); }));
  rClose.addEventListener('click',closeRent); rCancel.addEventListener('click',closeRent);
  rModal.addEventListener('click',e=>{ if(e.target===rModal) closeRent(); });
  document.addEventListener('keydown',e=>{ if(e.key==='Escape' && rModal.classList.contains('open')) closeRent(); });
  rReceive.addEventListener('change',()=>{ if(rReceive.value){ rReturn.min=rReceive.value; if(rReturn.value < rReceive.value) rReturn.value=rReceive.value; } });
  rForm.addEventListener('submit',function(e){
    e.preventDefault();
    rMsg.style.color=''; rMsg.innerHTML='<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
    rSubmit.disabled=true;
    const fd=new FormData(rForm); fd.append('action','add');
    fetch('ajax_cart.php',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(r=>r.json())
      .then(d=>{
        if(d && d.ok){ window.location.href='checkout.php'; return; }
        rMsg.style.color='var(--danger,#dc3545)';
        rMsg.textContent=(d && d.message) ? d.message : 'Không thể đặt thuê. Vui lòng thử lại.';
        rSubmit.disabled=false;
      }).catch(()=>{ rMsg.style.color='var(--danger,#dc3545)'; rMsg.textContent='Lỗi kết nối. Vui lòng thử lại.'; rSubmit.disabled=false; });
  });
})();
</script>

<?php include 'footer.php'; ?>

