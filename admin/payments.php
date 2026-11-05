<?php
include 'header.php';
$error='';
try{
if(isset($_POST['save_payment'])){
    $rentalId=(int)($_POST['rental_id']??0);$amount=normalize_price($_POST['amount']??0);$type=$_POST['payment_type']??'rental';$method=$_POST['method']??'cash';$note=trim($_POST['note']??'');
    if($rentalId<=0||$amount<=0)throw new Exception('Vui lòng chọn phiếu thuê và nhập số tiền');
    if(!in_array($type,['deposit','rental','refund'],true)||!in_array($method,['cash','bank','card'],true))throw new Exception('Thông tin thanh toán không hợp lệ');
    $stmt=$conn->prepare("SELECT final_amount,paid_amount,status FROM rentals WHERE id=?");$stmt->bind_param('i',$rentalId);$stmt->execute();$rental=$stmt->get_result()->fetch_assoc();
    if(!$rental)throw new Exception('Không tìm thấy phiếu thuê');
    if($rental['status']==='cancelled'&&$type!=='refund')throw new Exception('Phiếu đã hủy không thể ghi nhận khoản thu');
    if($type==='refund'){
        if($amount>(float)$rental['paid_amount'])throw new Exception('Số tiền hoàn không được lớn hơn số đã thu');
    }else{
        $remaining=(float)$rental['final_amount']-(float)$rental['paid_amount'];
        if($remaining<=0)throw new Exception('Phiếu thuê đã thanh toán đủ');
        if($amount>$remaining)throw new Exception('Số tiền thu vượt quá số còn phải thanh toán');
    }
    $stmt=$conn->prepare("INSERT INTO payments(rental_id,amount,payment_type,method,note) VALUES(?,?,?,?,?)");$stmt->bind_param('idsss',$rentalId,$amount,$type,$method,$note);$stmt->execute();
    sync_rental_payment($conn,$rentalId);header('Location: payments.php');exit;
}
if(isset($_GET['delete'])){
    $id=(int)$_GET['delete'];$stmt=$conn->prepare("SELECT rental_id FROM payments WHERE id=?");$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
    if(!$row)throw new Exception('Không tìm thấy giao dịch');
    $stmt=$conn->prepare("DELETE FROM payments WHERE id=?");$stmt->bind_param('i',$id);$stmt->execute();sync_rental_payment($conn,(int)$row['rental_id']);header('Location: payments.php');exit;
}
}catch(Throwable $e){$error=$e->getMessage();}
$page=max(1,(int)($_GET['page']??1));$limit=12;$offset=($page-1)*$limit;$keyword=trim($_GET['q']??'');
if($keyword!==''){$like='%'.$keyword.'%';$stmt=$conn->prepare("SELECT p.*,r.rental_code,c.fullname FROM payments p JOIN rentals r ON r.id=p.rental_id JOIN customers c ON c.id=r.customer_id WHERE r.rental_code LIKE ? OR c.fullname LIKE ? ORDER BY p.id DESC LIMIT ?,?");$stmt->bind_param('ssii',$like,$like,$offset,$limit);$stmt->execute();$data=$stmt->get_result();$stmt=$conn->prepare("SELECT COUNT(*) total FROM payments p JOIN rentals r ON r.id=p.rental_id JOIN customers c ON c.id=r.customer_id WHERE r.rental_code LIKE ? OR c.fullname LIKE ?");$stmt->bind_param('ss',$like,$like);$stmt->execute();$total=(int)$stmt->get_result()->fetch_assoc()['total'];}
else{$data=$conn->query("SELECT p.*,r.rental_code,c.fullname FROM payments p JOIN rentals r ON r.id=p.rental_id JOIN customers c ON c.id=r.customer_id ORDER BY p.id DESC LIMIT $offset,$limit");$total=(int)$conn->query("SELECT COUNT(*) total FROM payments")->fetch_assoc()['total'];}
$pages=max(1,(int)ceil($total/$limit));
$rentals=$conn->query("SELECT r.id,r.rental_code,r.final_amount,r.paid_amount,r.status,c.fullname FROM rentals r JOIN customers c ON c.id=r.customer_id WHERE r.status<>'cancelled' OR r.paid_amount>0 ORDER BY r.id DESC");
$typeLabels=['deposit'=>'Tiền cọc','rental'=>'Tiền thuê','refund'=>'Hoàn tiền'];$methodLabels=['cash'=>'Tiền mặt','bank'=>'Chuyển khoản','card'=>'Thẻ'];
?>
<?php if($error):?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif;?>
<div class="page-head"><div><h2>Thanh toán</h2><p>Quản lý tiền thuê, tiền cọc và hoàn tiền.</p></div><button class="btn-primary-soft" data-bs-toggle="modal" data-bs-target="#paymentModal" onclick="openPayment()"><i class="fas fa-plus"></i>Thêm giao dịch</button></div>
<form class="filter-bar"><div class="search-box"><i class="fas fa-search"></i><input name="q" value="<?= htmlspecialchars($keyword) ?>" placeholder="Tìm mã phiếu hoặc khách hàng"></div><button class="filter-submit">Tìm kiếm</button><?php if($keyword!==''):?><a href="payments.php">Xóa lọc</a><?php endif;?></form>
<section class="panel table-panel"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Thời gian</th><th>Mã phiếu</th><th>Khách hàng</th><th>Loại giao dịch</th><th>Phương thức</th><th>Số tiền</th><th>Ghi chú</th><th class="text-end">Thao tác</th></tr></thead><tbody>
<?php if($data&&$data->num_rows):while($row=$data->fetch_assoc()):?><tr><td><?= date('d/m/Y H:i',strtotime($row['created_at'])) ?></td><td><strong><?= htmlspecialchars($row['rental_code']) ?></strong></td><td><?= htmlspecialchars($row['fullname']) ?></td><td><span class="payment-type type-<?= $row['payment_type'] ?>"><?= $typeLabels[$row['payment_type']] ?></span></td><td><?= $methodLabels[$row['method']] ?></td><td><strong class="<?= $row['payment_type']==='refund'?'text-danger':'' ?>"><?= $row['payment_type']==='refund'?'-':'' ?><?= format_price($row['amount']) ?>đ</strong></td><td><?= htmlspecialchars($row['note']?:'Không có') ?></td><td class="text-end"><button class="icon-btn danger" onclick="removePayment(<?= $row['id'] ?>)"><i class="fas fa-trash"></i></button></td></tr><?php endwhile;else:?><tr><td colspan="8" class="empty">Chưa có giao dịch.</td></tr><?php endif;?>
</tbody></table></div><?php if($pages>1):?><div class="pagination-wrap"><?php for($i=1;$i<=$pages;$i++):?><a class="<?= $i===$page?'active':'' ?>" href="?page=<?= $i ?>&q=<?= urlencode($keyword) ?>"><?= $i ?></a><?php endfor;?></div><?php endif;?></section>
<div class="modal fade" id="paymentModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><form method="post" class="modal-content"><div class="modal-header"><h5>Thêm giao dịch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-12"><label>Phiếu thuê</label><select class="form-select" name="rental_id" id="payment_rental" required><option value="">Chọn phiếu thuê</option><?php while($r=$rentals->fetch_assoc()):?><option value="<?= $r['id'] ?>" data-remaining="<?= max(0,(float)$r['final_amount']-(float)$r['paid_amount']) ?>" data-paid="<?= (float)$r['paid_amount'] ?>"><?= htmlspecialchars($r['rental_code']) ?> · <?= htmlspecialchars($r['fullname']) ?> · Còn <?= format_price(max(0,(float)$r['final_amount']-(float)$r['paid_amount'])) ?>đ</option><?php endwhile;?></select></div><div class="col-md-4"><label>Loại giao dịch</label><select class="form-select" name="payment_type" id="payment_type"><option value="rental">Tiền thuê</option><option value="deposit">Tiền cọc</option><option value="refund">Hoàn tiền</option></select></div><div class="col-md-4"><label>Phương thức</label><select class="form-select" name="method"><option value="cash">Tiền mặt</option><option value="bank">Chuyển khoản</option><option value="card">Thẻ</option></select></div><div class="col-md-4"><label>Số tiền</label><input class="form-control money-input" inputmode="numeric" name="amount" id="payment_amount" required></div><div class="col-12"><div class="payment-hint" id="payment_hint">Chọn phiếu thuê để xem số tiền còn lại.</div></div><div class="col-12"><label>Ghi chú</label><textarea class="form-control" rows="3" name="note"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button><button class="btn-primary-soft" name="save_payment">Lưu giao dịch</button></div></form></div></div>
<script>
function openPayment(){document.getElementById('payment_rental').value='';document.getElementById('payment_type').value='rental';document.getElementById('payment_amount').value='';document.getElementById('payment_hint').textContent='Chọn phiếu thuê để xem số tiền còn lại.';}
function updateHint(){const option=document.getElementById('payment_rental').selectedOptions[0],type=document.getElementById('payment_type').value;if(!option||!option.value)return;const value=type==='refund'?Number(option.dataset.paid):Number(option.dataset.remaining);document.getElementById('payment_hint').textContent=type==='refund'?'Có thể hoàn tối đa '+value.toLocaleString('en-US')+'đ':'Còn phải thu '+value.toLocaleString('en-US')+'đ';}
document.getElementById('payment_rental').addEventListener('change',updateHint);document.getElementById('payment_type').addEventListener('change',updateHint);
function removePayment(id){Swal.fire({title:'Xóa giao dịch?',text:'Trạng thái thanh toán của phiếu sẽ được tính lại.',icon:'warning',showCancelButton:true,confirmButtonText:'Xóa',cancelButtonText:'Hủy',confirmButtonColor:'#d66c7a'}).then(r=>{if(r.isConfirmed)location.href='?delete='+id;});}
</script>
<?php include 'footer.php'; ?>