<?php
require_once '../customer_auth.php';
if (!customer_logged_in()) { header('Location: login.php'); exit; }
$customerId = (int)($_SESSION['customer_id'] ?? 0);
$error=''; $success=false; $rentalId=0; $rentalCode=''; $depositTxn=''; $depositAmount=0;

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['confirm_deposit'])){
  try{
    $rentalId=(int)($_POST['rental_id'] ?? 0);
    $depositTxn=trim($_POST['deposit_txn'] ?? '');
    if($rentalId<=0) throw new Exception('Phiếu thuê không hợp lệ');
    $conn->begin_transaction();
    $stmt=$conn->prepare("SELECT id,customer_id,status,deposit_amount,receive_date,return_date,rental_amount,final_amount FROM rentals WHERE id=? FOR UPDATE");
    if(!$stmt) throw new Exception('Lỗi truy vấn: '.$conn->error);
    $stmt->bind_param('i',$rentalId);$stmt->execute();$r=$stmt->get_result()->fetch_assoc();
    if(!$r) throw new Exception('Không tìm thấy phiếu thuê');
    if((int)$r['customer_id'] !== $customerId) throw new Exception('Bạn không có quyền xác nhận phiếu này');
    if(in_array($r['status'],['cancelled','completed'],true)) throw new Exception('Phiếu thuê không ở trạng thái chờ cọc');
    // nếu đã cọc rồi thì coi như thành công
    if(in_array($r['status'],['deposited','confirmed','picked_up','returned','overdue'],true)){
      $conn->commit(); $success=true; $rentalCode='PT'.str_pad((string)$rentalId,5,'0',STR_PAD_LEFT); $depositAmount=(float)$r['deposit_amount'];
    } else {
      // kiểm tra còn hạn 15 phút dựa trên session nếu có
      $pending=$_SESSION['pending_rental'] ?? null;
      if($pending && (int)($pending['rental_id']??0)===$rentalId && time() > (int)($pending['expires_at']??0)){
        $conn->query("UPDATE rentals SET status='cancelled' WHERE id=".$rentalId." AND status='pending'");
        $conn->commit(); throw new Exception('Phiếu đã hết thời gian chờ 15 phút. Vui lòng tạo lại.');
      }
      $depositAmount=(float)$r['deposit_amount'];
      // kiểm tra lại lịch trống trước khi khóa
      $itq=$conn->prepare("SELECT ri.costume_id,ri.quantity FROM rental_items ri WHERE ri.rental_id=?");
      $itq->bind_param('i',$rentalId);$itq->execute();$items=$itq->get_result()->fetch_all(MYSQLI_ASSOC);
      foreach($items as $it){
        $avail=available_quantity($conn,(int)$it['costume_id'],$r['receive_date'],$r['return_date'],$rentalId);
        if($avail < (int)$it['quantity']) throw new Exception('Một số trang phục đã hết chỗ trong thời gian này, không thể xác nhận cọc');
      }
      $conn->query("UPDATE rentals SET status='deposited', payment_status='partial' WHERE id=".$rentalId." AND status IN ('pending','reserved')");
      $ptype='deposit'; $pm='bank'; $pnote='Xác nhận đã nhận cọc chuyển khoản (QR) - khách hàng';
      $txn=$depositTxn!==''?$depositTxn:('PT'.str_pad((string)$rentalId,5,'0',STR_PAD_LEFT).'_'.$customerId);
      $pst=$conn->prepare("INSERT INTO payments(rental_id,amount,payment_type,payment_method,note,transaction_code,status,paid_at) VALUES(?,?,?,?,?,?, 'completed',NOW())");
      if($pst){ $pst->bind_param('idssss',$rentalId,$depositAmount,$ptype,$pm,$pnote,$txn); }
      else { $pnote2=$pnote.' | Mã TT: '.$txn; $pst=$conn->prepare("INSERT INTO payments(rental_id,amount,payment_type,method,note) VALUES(?,?,?,?,?)"); if(!$pst) throw new Exception('Không thể ghi nhận cọc: '.$conn->error); $pst->bind_param('idsss',$rentalId,$depositAmount,$ptype,$pm,$pnote2); }
      $pst->execute();
      if(function_exists('sync_rental_payment')) sync_rental_payment($conn,$rentalId);
      if(function_exists('sync_rental_deposit_paid')) @sync_rental_deposit_paid($conn,$rentalId);
      $conn->commit();
      $success=true; $rentalCode='PT'.str_pad((string)$rentalId,5,'0',STR_PAD_LEFT);
      unset($_SESSION['pending_rental']); $_SESSION['cart']=[];
    }
  }catch(Throwable $e){ @$conn->rollback(); $error=$e->getMessage(); }
} elseif($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pay_deposit'])){
  // tương thích luồng cũ (nếu còn)
  $error='Luồng thanh toán đã được cập nhật. Vui lòng quay lại giỏ/chi tiết đơn để tạo phiếu chờ cọc và xác nhận.';
} else {
  $error='Yêu cầu không hợp lệ.';
}

$pageTitle='Kết quả thanh toán cọc – Lumi Dress';
include 'header.php';
?>
<section class="section"><div class="container">
  <?php if($success): ?>
    <?php $qrContent=$depositTxn!==''?$depositTxn:('PT'.str_pad((string)$rentalId,5,'0',STR_PAD_LEFT)); $qrUrl='https://api.qrserver.com/v1/create-qr-code/?size=180x180&data='.urlencode($qrContent); ?>
    <div class="empty-state" style="padding:40px 0;">
      <i class="fas fa-circle-check" style="color:#2e7d32;"></i>
      <h3>Đã xác nhận cọc & khóa lịch</h3>
      <p>Phiếu thuê <strong><?= htmlspecialchars($rentalCode) ?></strong> đã chuyển sang trạng thái <strong>Đã cọc</strong>. Lịch thuê đã được khóa.</p>
      <p>Nội dung/Mã thanh toán cọc: <strong style="color:#4b2fb6;word-break:break-all;"><?= htmlspecialchars($depositTxn!==''?$depositTxn:$rentalCode) ?></strong></p>
      <?php if($depositAmount>0): ?><p>Số tiền cọc: <strong><?= format_price($depositAmount) ?>đ</strong></p><?php endif; ?>
      <div style="margin:12px 0;"><img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR mã cọc" width="180" height="180" style="border-radius:8px;background:#fff;padding:6px;"></div>
      <p><a href="profile.php" style="color:var(--primary)">Xem phiếu thuê của tôi</a> · <a href="products.php" style="color:var(--primary)">Tiếp tục xem trang phục</a></p>
    </div>
  <?php else: ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error?:'Không thể xử lý thanh toán.') ?></div>
    <p><a href="checkout.php">Quay lại chi tiết đơn</a> · <a href="cart.php">Giỏ thuê</a></p>
  <?php endif; ?>
</div></section>
<?php include 'footer.php'; ?>
