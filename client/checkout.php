<?php
require_once '../customer_auth.php';
if (!customer_logged_in()) { header('Location: login.php?redirect=checkout.php'); exit; }
$customerId = (int)($_SESSION['customer_id'] ?? 0);
$cart = $_SESSION['cart'] ?? [];
$error=''; $created=false; $rentalId=0; $rentalCode=''; $depositTxn=''; $delivery='store'; $expiresAt=0;
$receiveMin=null; $returnMax=null;

function tier_deposit_line_local($catName, $rentalPrice, $qty){
  $name=mb_strtolower((string)$catName);
  $listed=max(0,(float)$rentalPrice)*max(1,(int)$qty)*10; // ước lượng giá trị niêm yết
  $isWedding=strpos($name,'váy cưới')!==false || strpos($name,'cuoi')!==false;
  $isVest=strpos($name,'vest')!==false;
  $isAoDai=strpos($name,'áo dài')!==false || strpos($name,'ao dai')!==false;
  $isDaHoi=strpos($name,'dạ hội')!==false || strpos($name,'da hoi')!==false;
  $isPerf=strpos($name,'biểu diễn')!==false || strpos($name,'bieu dien')!==false;
  if($isWedding || $listed>3000000*$qty){
    $dep=$listed*0.7; $min=1500000*$qty; $max=3000000*$qty; // Hạng 3
  }elseif($isVest || $isDaHoi || $listed>=1000000*$qty){
    $dep=$listed*0.5; $min=500000*$qty; $max=1000000*$qty; // Hạng 2
  }else{
    return 200000.0*$qty; // Hạng 1 (phổ thông, áo dài, biểu diễn)
  }
  if($dep<$min) $dep=$min; if($dep>$max) $dep=$max;
  return round($dep, -3);
}

function build_cart_items($conn,$cart){
  $items=[];$rent=0;$deposit=0;$receiveMin=null;$returnMax=null;
  foreach($cart as $key=>$line){
    $id=(int)($line['id']??0); $qty=max(1,(int)($line['qty']??1));
    $receive=$line['receive']??''; $ret=$line['return']??'';
    if($id<=0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$receive) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$ret)) continue;
    $stmt=$conn->prepare("SELECT c.id,c.name,c.size,c.rental_price,c.deposit,c.status,cat.name AS cat_name FROM costumes c LEFT JOIN categories cat ON cat.id=c.category_id WHERE c.id=?");
    $stmt->bind_param('i',$id);$stmt->execute();$p=$stmt->get_result()->fetch_assoc(); if(!$p) continue;
    $receiveMin = $receiveMin===null||$receive<$receiveMin ? $receive : $receiveMin;
    $returnMax = $returnMax===null||$ret>$returnMax ? $ret : $returnMax;
    $days=rental_days_count($receive,$ret);
    $lineRent=(float)$p['rental_price']*$qty*$days;
    $lineDep=tier_deposit_line_local($p['cat_name']??'', (float)$p['rental_price'], $qty);
    $items[]=['costume_id'=>$id,'qty'=>$qty,'receive'=>$receive,'return'=>$ret,'days'=>$days,'size'=>$p['size'],'name'=>$p['name'],'cat'=>$p['cat_name']??'','unit'=>(float)$p['rental_price'],'subtotal'=>$lineRent,'dep'=>$lineDep];
    $rent+=$lineRent; $deposit+=$lineDep;
  }
  return [$items,$rent,$deposit,$receiveMin,$returnMax];
}

list($items,$rentTotal,$depositTotal,$receiveMin,$returnMax)=build_cart_items($conn,$cart);
// lấy SĐT khách
$custPhone='';
$cq=$conn->prepare("SELECT phone,fullname FROM customers WHERE id=?");$cq->bind_param('i',$customerId);$cq->execute();$cust=$cq->get_result()->fetch_assoc();
if($cust){ $custPhone=preg_replace('/\D+/','',(string)$cust['phone']); }

$pending = $_SESSION['pending_rental'] ?? null;
if($pending && (int)($pending['customer_id']??0)!==$customerId){ $pending=null; unset($_SESSION['pending_rental']); }
if($pending && time() > (int)($pending['expires_at']??0)){
  // hết hạn: hủy phiếu tạm nếu còn pending
  $pid=(int)($pending['rental_id']??0);
  if($pid>0){ @$conn->query("UPDATE rentals SET status='cancelled' WHERE id=".$pid." AND status='pending'"); }
  unset($_SESSION['pending_rental']); $pending=null;
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_pending'])){
  try{
    $delivery = ($_POST['delivery_method'] ?? 'store')==='delivery' ? 'delivery' : 'store';
    if(empty($items)) throw new Exception('Giỏ thuê trống hoặc dữ liệu không hợp lệ');
    $conn->begin_transaction();
    foreach($items as $it){
      $avail=available_quantity($conn,$it['costume_id'],$receiveMin,$returnMax,0);
      if($avail < $it['qty']) throw new Exception($it['name'].' không đủ số lượng trống, vui lòng chọn lại');
    }
    $note='Đặt thuê online (QR) - Chờ xác nhận cọc | Nhận đồ: '.($delivery==='delivery'?'Giao tận nơi':'Nhận tại cửa hàng');
    $final=$rentTotal+$depositTotal;
    $status='pending'; $pstatus='unpaid';
    $stmt=$conn->prepare("INSERT INTO rentals(customer_id,receive_date,return_date,rental_amount,deposit_amount,discount,final_amount,note,status,payment_status) VALUES(?,?,?,?,?,0,?,?,?,?)");
    if(!$stmt) throw new Exception('Không thể tạo phiếu thuê: '.$conn->error);
    $stmt->bind_param('issdddsss',$customerId,$receiveMin,$returnMax,$rentTotal,$depositTotal,$final,$note,$status,$pstatus);
    $stmt->execute(); $rentalId=$conn->insert_id;
    $ist=$conn->prepare("INSERT INTO rental_items(rental_id,costume_id,quantity,selected_size,unit_price,rental_days,subtotal,deposit_amount,condition_out) VALUES(?,?,?,?,?,?,?,?,?)");
    if(!$ist) throw new Exception('Không thể lưu chi tiết phiếu: '.$conn->error);
    foreach($items as $it){
      $cond='Tốt';
      $ist->bind_param('iiisdddds',$rentalId,$it['costume_id'],$it['qty'],$it['size'],$it['unit'],$it['days'],$it['subtotal'],$it['dep'],$cond);
      $ist->execute();
    }
    $rentalCode='PT'.str_pad((string)$rentalId,5,'0',STR_PAD_LEFT);
    $depositTxn=$rentalCode.'_'.($custPhone!==''?$custPhone:$customerId);
    $expiresAt=time()+15*60;
    $conn->commit();
    $_SESSION['pending_rental']=['rental_id'=>$rentalId,'customer_id'=>$customerId,'rental_code'=>$rentalCode,'deposit_txn'=>$depositTxn,'deposit_amount'=>$depositTotal,'rent_amount'=>$rentTotal,'expires_at'=>$expiresAt,'delivery'=>$delivery];
    $pending=$_SESSION['pending_rental']; $created=true;
  }catch(Throwable $e){ @$conn->rollback(); $error=$e->getMessage(); }
}

if($pending){
  $rentalId=(int)$pending['rental_id']; $rentalCode=$pending['rental_code']??('PT'.str_pad((string)$rentalId,5,'0',STR_PAD_LEFT));
  $depositTxn=$pending['deposit_txn']??''; $depositTotal=(float)($pending['deposit_amount']??$depositTotal);
  $rentTotal=(float)($pending['rent_amount']??$rentTotal); $expiresAt=(int)($pending['expires_at']??0); $delivery=$pending['delivery']??'store';
  $created=true;
}

$pageTitle='Chi tiết đơn thuê – Lumi Dress';
include 'header.php';
$qrContent = $depositTxn!=='' ? ($depositTxn.'|AMOUNT='.((int)$depositTotal)) : '';
$qrUrl = $qrContent!=='' ? ('https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . urlencode($qrContent)) : '';
?>
<section class="section" style="background:linear-gradient(180deg,#faf8ff 0%,#fff 45%);">
  <div class="container">
    <div class="section-head" style="margin-bottom:24px;">
      <span class="section-eyebrow">Đặt thuê</span>
      <h2 class="section-title">Chi tiết đơn thuê & thanh toán cọc</h2>
      <p style="color:#666;margin-top:8px;">Tạo phiếu ở trạng thái <strong>Chờ xác nhận cọc</strong>, quét QR chuyển khoản đúng số tiền và nội dung, sau đó xác nhận để khóa lịch.</p>
    </div>
    <?php if($error):?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif;?>
    <?php if(empty($items) && !$created): ?>
      <div class="empty-state"><i class="fas fa-bag-shopping"></i><h3>Giỏ thuê trống</h3><p><a href="products.php">Chọn trang phục</a> trước khi thanh toán.</p></div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:minmax(0,1.25fr) minmax(340px,.85fr);gap:24px;align-items:start;">
        <div style="display:grid;gap:18px;">
          <div class="info-card" style="padding:22px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px;">
              <h4 style="margin:0;"><i class="fas fa-receipt" style="color:var(--primary);margin-right:8px;"></i>Thông tin đơn thuê</h4>
              <?php if($created): ?><span style="background:#fff4e5;color:#9a5b00;padding:6px 12px;border-radius:999px;font-size:13px;font-weight:600;">Chờ xác nhận cọc · <?= htmlspecialchars($rentalCode) ?></span><?php else: ?><span style="background:#f3edff;color:#5a3ec8;padding:6px 12px;border-radius:999px;font-size:13px;font-weight:600;"><?= count($items) ?> sản phẩm</span><?php endif; ?>
            </div>
            <div style="display:grid;gap:12px;">
              <?php foreach($items as $it): ?>
                <div style="border:1px solid #eee;border-radius:14px;padding:14px 16px;background:#fff;">
                  <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div>
                      <div style="font-weight:700;font-size:16px;color:#222;"><?= htmlspecialchars($it['name']) ?></div>
                      <div style="color:#666;font-size:14px;margin-top:4px;">
                        <i class="far fa-calendar-alt"></i> <?= date('d/m/Y',strtotime($it['receive'])) ?> → <?= date('d/m/Y',strtotime($it['return'])) ?> · <?= $it['days'] ?> ngày · SL <?= $it['qty'] ?>
                      </div>
                    </div>
                    <div style="text-align:right;">
                      <div style="font-size:13px;color:#777;">Tiền thuê</div>
                      <div style="font-weight:700;color:#222;"><?= format_price($it['subtotal']) ?>đ</div>
                      <div style="font-size:13px;color:#777;margin-top:4px;">Cọc (theo hạng): <strong><?= format_price($it['dep']) ?>đ</strong></div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="info-card" style="padding:20px 22px;background:#fff;">
            <h4 style="margin:0 0 10px 0;"><i class="fas fa-circle-info" style="color:var(--primary);margin-right:8px;"></i>Quy định thanh toán cọc & giữ chỗ</h4>
            <ul style="margin:0;padding-left:18px;color:#555;line-height:1.7;">
              <li>Nội dung chuyển khoản: <strong><?= htmlspecialchars($depositTxn!==''?$depositTxn:'[MaPhieuThue_SoDienThoai]') ?></strong> (mã phiếu + SĐT).</li>
              <li>Thời gian chờ thanh toán cọc: <strong>15 phút</strong> kể từ khi tạo phiếu. Hết hạn chưa xác nhận cọc, phiếu tạm sẽ được hủy để giải phóng lịch.</li>
              <li>Sau khi chuyển khoản thành công, nhấn <strong>"Xác nhận đã thanh toán cọc"</strong> để hệ thống ghi nhận và khóa lịch.</li>
            </ul>
          </div>
        </div>

        <div class="info-card" style="padding:22px;position:sticky;top:90px;border:1px solid #efe7ff;box-shadow:0 10px 30px rgba(80,50,160,.08);">
          <h4 style="margin:0 0 14px 0;"><i class="fas fa-credit-card" style="color:var(--primary);margin-right:8px;"></i>Thanh toán cọc (QR/CK)</h4>
          <div style="display:grid;gap:10px;padding:14px;border-radius:12px;background:#faf8ff;border:1px solid #efe7ff;">
            <div style="display:flex;justify-content:space-between;"><span style="color:#555;">Tiền thuê dự kiến</span><strong><?= format_price($rentTotal) ?>đ</strong></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:#555;">Tiền cọc (theo hạng)</span><strong style="color:#5a3ec8;"><?= format_price($depositTotal) ?>đ</strong></div>
            <div style="display:flex;justify-content:space-between;padding-top:10px;border-top:1px dashed #d9cfff;font-size:17px;"><span>Tổng dự kiến</span><strong style="color:var(--primary);"><?= format_price($rentTotal+$depositTotal) ?>đ</strong></div>
          </div>

          <?php if(!$created): ?>
            <form method="post" style="margin-top:16px;">
              <label style="font-weight:600;display:block;margin-bottom:8px;">Hình thức nhận đồ</label>
              <div style="display:grid;gap:8px;margin-bottom:14px;">
                <label style="display:flex;gap:8px;align-items:center;"><input type="radio" name="delivery_method" value="store" checked> Nhận tại cửa hàng</label>
                <label style="display:flex;gap:8px;align-items:center;"><input type="radio" name="delivery_method" value="delivery"> Giao tận nơi</label>
              </div>
              <button class="btn-rent-now" name="create_pending" value="1" style="width:100%;justify-content:center;padding:14px 18px;font-size:16px;"><i class="fas fa-qrcode"></i> Tạo phiếu & hiển thị mã QR cọc</button>
            </form>
            <p style="margin:10px 0 0;font-size:13px;color:#777;text-align:center;">Phiếu sẽ ở trạng thái <strong>Chờ xác nhận cọc</strong> và giữ chỗ trong 15 phút.</p>
          <?php else: ?>
            <div style="margin:12px 0;padding:10px 12px;border-radius:10px;background:#fff4e5;color:#8a5200;font-weight:600;text-align:center;">Thời gian chờ còn lại: <span id="countdown">15:00</span></div>
            <div style="margin:12px 0 0;padding:16px;border:1px dashed #d6c7ff;border-radius:14px;background:linear-gradient(180deg,#fcfaff,#f7f2ff);text-align:center;">
              <div style="font-size:13px;color:#666;margin-bottom:8px;">Quét mã QR để chuyển khoản cọc</div>
              <?php if($qrUrl): ?><img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR thanh toán cọc" width="240" height="240" style="max-width:100%;height:auto;border-radius:12px;background:#fff;padding:8px;border:1px solid #eee;"><?php endif; ?>
              <div style="font-size:13px;color:#666;margin-top:12px;">Nội dung chuyển khoản (Mã phiếu + SĐT)</div>
              <div style="font-size:20px;font-weight:800;letter-spacing:.4px;color:#4b2fb6;margin-top:2px;word-break:break-all;"><?= htmlspecialchars($depositTxn) ?></div>
              <div style="font-size:14px;color:#444;margin-top:4px;">Số tiền cọc: <strong><?= format_price($depositTotal) ?>đ</strong></div>
              <div style="font-size:13px;color:#666;margin-top:4px;">Nhận đồ: <strong><?= $delivery==='delivery'?'Giao tận nơi':'Nhận tại cửa hàng' ?></strong></div>
            </div>
            <form method="post" action="payment_confirm.php" style="margin-top:16px;">
              <input type="hidden" name="rental_id" value="<?= (int)$rentalId ?>">
              <input type="hidden" name="deposit_txn" value="<?= htmlspecialchars($depositTxn) ?>">
              <button class="btn-rent-now" name="confirm_deposit" value="1" style="width:100%;justify-content:center;padding:14px 18px;font-size:16px;"><i class="fas fa-check-circle"></i> Xác nhận đã thanh toán cọc</button>
            </form>
            <p id="expireMsg" style="display:none;margin:10px 0 0;color:#b00020;font-size:13px;text-align:center;">Đã hết thời gian chờ. Vui lòng tạo lại phiếu.</p>
            <script>
              (function(){var exp=<?= (int)$expiresAt ?>*1000;var el=document.getElementById('countdown');var msg=document.getElementById('expireMsg');function t(){var d=Math.max(0,Math.floor((exp-Date.now())/1000));var m=Math.floor(d/60),s=d%60;if(el)el.textContent=String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');if(d<=0){if(msg)msg.style.display='block';clearInterval(iv);}}t();var iv=setInterval(t,1000);})();
            </script>
          <?php endif; ?>
          <a href="cart.php" style="display:inline-flex;width:100%;justify-content:center;margin-top:10px;color:#666;text-decoration:none;font-size:14px;"><i class="fas fa-arrow-left" style="margin-right:6px;"></i> Quay lại giỏ thuê</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include 'footer.php'; ?>
