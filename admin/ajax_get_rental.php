<?php
session_start();
require_once '../config.php';
if(!isset($_SESSION['admin'])){http_response_code(403);exit('Không có quyền truy cập');}
$id=(int)($_GET['id']??0);
$stmt=$conn->prepare("SELECT r.*,c.fullname,c.phone,c.email,c.identity_number,c.address, COALESCE((SELECT SUM(ri.unit_price*ri.quantity) FROM rental_items ri WHERE ri.rental_id=r.id),0) AS daily_rate FROM rentals r JOIN customers c ON c.id=r.customer_id WHERE r.id=?");
$stmt->bind_param('i',$id);$stmt->execute();$rental=$stmt->get_result()->fetch_assoc();
if(!$rental){
    if(isset($_GET['html'])){echo '<div class="alert alert-danger">Không tìm thấy phiếu thuê.</div>';}
    else{header('Content-Type: application/json');echo json_encode(['success'=>false,'message'=>'Không tìm thấy phiếu thuê']);}
    exit;
}
$stmt=$conn->prepare("SELECT ri.*,co.id AS code,co.name,co.image,co.color,co.rental_price FROM rental_items ri JOIN costumes co ON co.id=ri.costume_id WHERE ri.rental_id=? ORDER BY ri.id");
$stmt->bind_param('i',$id);$stmt->execute();$items=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// đồng bộ tiền cọc đã thu (server-side)
$dpStmt=$conn->prepare("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE rental_id=? AND payment_type='deposit' AND status IN ('completed','paid','refunded')");
if($dpStmt){ $dpStmt->bind_param('i',$id); $dpStmt->execute(); $dp=(float)$dpStmt->get_result()->fetch_assoc()['s']; $rental['deposit_paid']=max((float)($rental['deposit_paid']??0),$dp); }
$payments=[];
// Lấy lịch sử thanh toán của phiếu thuê
$payments = [];

$pst = $conn->prepare("
    SELECT
        id,
        amount,
        payment_type,
        payment_method,
        transaction_code,
        status,
        paid_at,
        refunded_at,
        note
    FROM payments
    WHERE rental_id = ?
    ORDER BY id DESC
");

if (!$pst) {
    throw new Exception('Lỗi truy vấn lịch sử thanh toán: ' . $conn->error);
}

$pst->bind_param('i', $id);
$pst->execute();

$payments = $pst->get_result()->fetch_all(MYSQLI_ASSOC);
$statusLabel=function_exists('rental_status_label_v2')?rental_status_label_v2($rental['status']):rental_status_label($rental['status']);
$lateFee = (float)($rental['late_fee'] ?? 0);
$damageFee = (float)($rental['damage_fee'] ?? 0);
$refundAmt = (float)($rental['refund_amount'] ?? 0);
$extraAmt = (float)($rental['extra_due_amount'] ?? 0);
$depositPaid = (float)($rental['deposit_paid'] ?? 0);
$discount = (float)($rental['discount'] ?? 0);

// Tiền thuê sau giảm giá
$netRental = max(
    0,
    (float)$rental['rental_amount'] - $discount
);

// Tổng chi phí thực tế
$tongThucTe = $netRental + $lateFee + $damageFee;

// Tiền thuê đã thanh toán trước
$paidStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) AS paid
    FROM payments
    WHERE rental_id = ?
      AND payment_type IN ('rental', 'extra')
      AND status IN ('completed', 'paid')
");

$paidStmt->bind_param('i', $id);
$paidStmt->execute();

$rentalPaid = (float)$paidStmt->get_result()->fetch_assoc()['paid'];

// Số tiền cần thu thêm hoặc hoàn
$soQuyetToan = $tongThucTe - $rentalPaid - $depositPaid;
if(!isset($_GET['html'])){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>true,'rental'=>$rental,'items'=>$items,'payments'=>$payments],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
?>
<div class="rental-detail-grid">
<section>
<div class="detail-title">
    <div>
        <span>Mã phiếu</span>
        <h3>
            PT<?= str_pad(
                (string)$rental['id'],
                5,
                '0',
                STR_PAD_LEFT
            ) ?>
        </h3>
    </div>

    <span class="status status-<?= htmlspecialchars($rental['status']) ?>">
        <?= htmlspecialchars($statusLabel) ?>
    </span>
</div>
<div class="detail-items">
<?php foreach($items as $item):?>
<article>
<img src="../uploads/<?= htmlspecialchars($item['image']) ?>" alt="">
<div><strong><?= htmlspecialchars($item['name']) ?></strong><span><?= htmlspecialchars($item['code']) ?> · Size <?= htmlspecialchars($item['selected_size']) ?> · <?= number_format($item['quantity']) ?> bộ</span><small><?= number_format($item['rental_days']) ?> ngày · <?= htmlspecialchars($item['condition_out']) ?></small></div>
<b><?= format_price($item['subtotal']) ?>đ</b>
</article>
<?php endforeach;?>
</div>
<div class="detail-money">
<div><span>Tiền thuê</span><strong><?= format_price($rental['rental_amount']) ?>đ</strong></div>
<div><span>Giảm giá</span><strong>-<?= format_price($rental['discount']) ?>đ</strong></div>
<div><span>Tiền cọc yêu cầu</span><strong><?= format_price($rental['deposit_amount']) ?>đ</strong></div>
<div><span>Tiền cọc đã thanh toán</span><strong class="text-success"><?= format_price($depositPaid) ?>đ</strong></div>
<?php if($lateFee>0):?><div><span>Phí trả muộn</span><strong><?= format_price($lateFee) ?>đ</strong></div><?php endif;?>
<?php if($damageFee>0):?><div><span>Phí hư hỏng</span><strong><?= format_price($damageFee) ?>đ</strong></div><?php endif;?>
<?php if($extraAmt>0):?><div><span>Cần thu thêm</span><strong class="text-danger"><?= format_price($extraAmt) ?>đ</strong></div><?php endif;?>
<?php if($refundAmt>0):?><div><span>Hoàn cọc lại</span><strong class="text-success"><?= format_price($refundAmt) ?>đ</strong></div><?php endif;?>
<div class="total"><span>Tổng chi phí thực tế</span><strong><?= format_price($tongThucTe) ?>đ</strong></div>
<div><span>So với cọc (quyết toán)</span><strong><?= $soQuyetToan>0?'+':'' ?><?= format_price($soQuyetToan) ?>đ</strong></div>
<?php if($soQuyetToan>0): ?><div><span>Kịch bản A (THU_THEM)</span><strong class="text-danger">Cần thu thêm <?= format_price($extraAmt>0?$extraAmt:$soQuyetToan) ?>đ</strong></div><?php elseif($soQuyetToan<0): ?><div><span>Kịch bản B (HOAN_COC)</span><strong class="text-success">Cọc thừa <?= format_price($refundAmt>0?$refundAmt:abs($soQuyetToan)) ?>đ</strong></div><?php endif; ?>
<div><span>Đã thanh toán</span><strong><?= format_price($rental['paid_amount']) ?>đ</strong></div>
</div>
<?php if(!empty($payments)): ?>
<div class="info-card" style="margin-top:12px;"><h4>Lịch sử thanh toán / cọc</h4>
<?php foreach($payments as $p):
  $m = $p['payment_method'] ?? '';
  $t=$p['transaction_code'] ?? '';
  $pl=function_exists('payment_type_label')?payment_type_label($p['payment_type']):$p['payment_type'];
  $isBank=in_array(strtolower((string)$m),['bank','transfer','chuyenkhoan','chuyen_khoan'],true);
  $qrUrl='';
  $isExtraPending = ($p['payment_type']==='extra' && in_array(($p['status'] ?? ''),['pending',''],true));
  if($t!=='' && ($isBank || $isExtraPending)){
    $qrContent='LUMIDRESS|'.$p['payment_type'].'|'.$t.'|AMOUNT='.(int)$p['amount'].'|RENTAL='.(int)$rental['id'];
    $qrUrl='https://api.qrserver.com/v1/create-qr-code/?size=180x180&data='.urlencode($qrContent);
  }
  $tag=''; if($p['payment_type']==='extra') $tag='THU_THEM'; elseif($p['payment_type']==='refund') $tag='HOAN_COC';
?>
  <div style="margin:8px 0;padding:10px;border:1px solid #eee;border-radius:10px;background:#fff;">
    <p style="margin:0 0 4px 0;font-size:13px;"><strong><?= htmlspecialchars($pl) ?></strong><?php if($tag): ?> <span style="color:#888;">(<?= $tag ?>)</span><?php endif; ?> · <?= format_price($p['amount']) ?>đ · <?= htmlspecialchars($m?:'N/A') ?><?php if($t): ?> · <span style="color:#5b4bdb;">Mã TT: <?= htmlspecialchars($t) ?></span><?php endif; ?><?php if(!empty($p['status'])): ?> · <em><?= htmlspecialchars($p['status']) ?></em><?php endif; ?></p>
    <?php if(!empty($p['note'])): ?><p style="margin:0;font-size:12px;color:#666;"><?= htmlspecialchars($p['note']) ?></p><?php endif; ?>
    <?php if($qrUrl): ?>
      <div style="display:flex;gap:12px;align-items:center;margin-top:10px;padding:10px;border:1px dashed #d6c7ff;border-radius:10px;background:#faf8ff;">
        <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR thanh toán" width="120" height="120" style="border-radius:8px;background:#fff;padding:4px;border:1px solid #eee;">
        <div style="font-size:13px;color:#444;">
          <div style="font-weight:600;color:#4b2fb6;margin-bottom:4px;"><i class="fas fa-qrcode"></i> <?= $p['payment_type']==='extra'?'QR thu thêm (động)':'Chuyển khoản' ?></div>
          <div>Mã TT/nội dung: <strong><?= htmlspecialchars($t) ?></strong></div>
          <div>Số tiền: <strong><?= format_price($p['amount']) ?>đ</strong></div>
          <div style="color:#777;font-size:12px;margin-top:4px;">Quét QR để thanh toán/đối soát giao dịch chuyển khoản.</div>
        </div>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>
<aside>
<div class="info-card"><h4>Khách hàng</h4><p><strong><?= htmlspecialchars($rental['fullname']) ?></strong></p><p><i class="fas fa-phone"></i><?= htmlspecialchars($rental['phone']) ?></p><p><i class="fas fa-envelope"></i><?= htmlspecialchars($rental['email']?:'Chưa có email') ?></p><p><i class="fas fa-id-card"></i><?= htmlspecialchars($rental['identity_number']?:'Chưa có CCCD') ?></p><p><i class="fas fa-location-dot"></i><?= htmlspecialchars($rental['address']?:'Chưa có địa chỉ') ?></p></div>
<div class="info-card"><h4>Thời gian thuê</h4><p><i class="fas fa-calendar-check"></i>Nhận dự kiến: <?= date('d/m/Y',strtotime($rental['receive_date'])) ?></p><p><i class="fas fa-calendar-xmark"></i>Trả dự kiến: <?= date('d/m/Y',strtotime($rental['return_date'])) ?></p><?php if(!empty($rental['actual_return_at'])):?><p><i class="fas fa-clock"></i>Trả thực tế: <?= date('d/m/Y H:i',strtotime($rental['actual_return_at'])) ?></p><?php endif;?><p><i class="fas fa-wallet"></i><?= payment_status_label($rental['payment_status']) ?></p></div>
<?php if($rental['note']):?><div class="info-card"><h4>Ghi chú</h4><p><?= nl2br(htmlspecialchars($rental['note'])) ?></p></div><?php endif;?>
<a class="detail-print" target="_blank" href="print_rental.php?id=<?= $rental['id'] ?>"><i class="fas fa-print"></i>In phiếu thuê</a>
</aside>
</div>