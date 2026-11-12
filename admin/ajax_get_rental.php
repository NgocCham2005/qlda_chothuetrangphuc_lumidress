<?php
session_start();
require_once '../config.php';
if(!isset($_SESSION['admin'])){http_response_code(403);exit('Không có quyền truy cập');}
$id=(int)($_GET['id']??0);
$stmt=$conn->prepare("SELECT r.*,c.fullname,c.phone,c.email,c.identity_number,c.address FROM rentals r JOIN customers c ON c.id=r.customer_id WHERE r.id=?");
$stmt->bind_param('i',$id);$stmt->execute();$rental=$stmt->get_result()->fetch_assoc();
if(!$rental){
    if(isset($_GET['html'])){echo '<div class="alert alert-danger">Không tìm thấy phiếu thuê.</div>';}
    else{header('Content-Type: application/json');echo json_encode(['success'=>false,'message'=>'Không tìm thấy phiếu thuê']);}
    exit;
}
$stmt=$conn->prepare("SELECT ri.*,co.id AS code,co.name,co.image,co.color FROM rental_items ri JOIN costumes co ON co.id=ri.costume_id WHERE ri.rental_id=? ORDER BY ri.id");
$stmt->bind_param('i',$id);$stmt->execute();$items=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if(!isset($_GET['html'])){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>true,'rental'=>$rental,'items'=>$items],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
?>
<div class="rental-detail-grid">
<section>
<div class="detail-title"><div><span>Mã phiếu</span><h3><?= htmlspecialchars($rental['id']) ?></h3></div><span class="status status-<?= $rental['status'] ?>"><?= rental_status_label($rental['status']) ?></span></div>
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
<div><span>Tiền cọc</span><strong><?= format_price($rental['deposit_amount']) ?>đ</strong></div>
<div class="total"><span>Tổng cần thu</span><strong><?= format_price($rental['final_amount']) ?>đ</strong></div>
<div><span>Đã thanh toán</span><strong><?= format_price($rental['paid_amount']) ?>đ</strong></div>
</div>
</section>
<aside>
<div class="info-card"><h4>Khách hàng</h4><p><strong><?= htmlspecialchars($rental['fullname']) ?></strong></p><p><i class="fas fa-phone"></i><?= htmlspecialchars($rental['phone']) ?></p><p><i class="fas fa-envelope"></i><?= htmlspecialchars($rental['email']?:'Chưa có email') ?></p><p><i class="fas fa-id-card"></i><?= htmlspecialchars($rental['identity_number']?:'Chưa có CCCD') ?></p><p><i class="fas fa-location-dot"></i><?= htmlspecialchars($rental['address']?:'Chưa có địa chỉ') ?></p></div>
<div class="info-card"><h4>Thời gian thuê</h4><p><i class="fas fa-calendar-check"></i>Nhận: <?= date('d/m/Y',strtotime($rental['receive_date'])) ?></p><p><i class="fas fa-calendar-xmark"></i>Trả: <?= date('d/m/Y',strtotime($rental['return_date'])) ?></p><p><i class="fas fa-wallet"></i><?= payment_status_label($rental['payment_status']) ?></p></div>
<?php if($rental['note']):?><div class="info-card"><h4>Ghi chú</h4><p><?= nl2br(htmlspecialchars($rental['note'])) ?></p></div><?php endif;?>
<a class="detail-print" target="_blank" href="print_rental.php?id=<?= $rental['id'] ?>"><i class="fas fa-print"></i>In phiếu thuê</a>
</aside>
</div>