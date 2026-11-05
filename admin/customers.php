<?php
include 'header.php';
$error='';
try{
if(isset($_POST['save'])){
    $id=(int)($_POST['id']??0);
    $fullname=trim($_POST['fullname']??'');
    $phone=trim($_POST['phone']??'');
    $email=trim($_POST['email']??'');
    $identity=trim($_POST['identity_number']??'');
    $address=trim($_POST['address']??'');
    $note=trim($_POST['note']??'');
    if($fullname===''||$phone==='')throw new Exception('Vui lòng nhập họ tên và số điện thoại');
    if(!preg_match('/^[0-9]{9,11}$/',$phone))throw new Exception('Số điện thoại không hợp lệ');
    if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new Exception('Email không hợp lệ');
    $check=$conn->prepare("SELECT id FROM customers WHERE phone=? AND id<>?");$check->bind_param('si',$phone,$id);$check->execute();
    if($check->get_result()->num_rows)throw new Exception('Số điện thoại đã tồn tại');
    if($id>0){$stmt=$conn->prepare("UPDATE customers SET fullname=?,phone=?,email=?,identity_number=?,address=?,note=? WHERE id=?");$stmt->bind_param('ssssssi',$fullname,$phone,$email,$identity,$address,$note,$id);}
    else{$stmt=$conn->prepare("INSERT INTO customers(fullname,phone,email,identity_number,address,note) VALUES(?,?,?,?,?,?)");$stmt->bind_param('ssssss',$fullname,$phone,$email,$identity,$address,$note);}
    $stmt->execute();
    header('Location: customers.php');exit;
}
if(isset($_GET['delete'])){
    $id=(int)$_GET['delete'];
    $stmt=$conn->prepare("SELECT COUNT(*) total FROM rentals WHERE customer_id=?");$stmt->bind_param('i',$id);$stmt->execute();
    if((int)$stmt->get_result()->fetch_assoc()['total']>0)throw new Exception('Khách hàng đã phát sinh phiếu thuê, không thể xóa');
    $stmt=$conn->prepare("DELETE FROM customers WHERE id=?");$stmt->bind_param('i',$id);$stmt->execute();
    header('Location: customers.php');exit;
}
}catch(Throwable $e){$error=$e->getMessage();}
$page=max(1,(int)($_GET['page']??1));$limit=10;$offset=($page-1)*$limit;$keyword=trim($_GET['q']??'');
$where='';$params=[];$types='';
if($keyword!==''){$where="WHERE fullname LIKE ? OR phone LIKE ? OR identity_number LIKE ?";$like='%'.$keyword.'%';}
if($where){$stmt=$conn->prepare("SELECT c.*,COUNT(r.id) rental_count FROM customers c LEFT JOIN rentals r ON r.customer_id=c.id $where GROUP BY c.id ORDER BY c.id DESC LIMIT ?,?");$stmt->bind_param('sssii',$like,$like,$like,$offset,$limit);$stmt->execute();$data=$stmt->get_result();$stmt=$conn->prepare("SELECT COUNT(*) total FROM customers $where");$stmt->bind_param('sss',$like,$like,$like);$stmt->execute();$total=(int)$stmt->get_result()->fetch_assoc()['total'];}
else{$data=$conn->query("SELECT c.*,COUNT(r.id) rental_count FROM customers c LEFT JOIN rentals r ON r.customer_id=c.id GROUP BY c.id ORDER BY c.id DESC LIMIT $offset,$limit");$total=(int)$conn->query("SELECT COUNT(*) total FROM customers")->fetch_assoc()['total'];}
$pages=max(1,(int)ceil($total/$limit));
?>
<?php if($error):?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif;?>
<div class="page-head"><div><h2>Khách hàng</h2><p>Quản lý thông tin liên hệ và lịch sử thuê.</p></div><button class="btn-primary-soft" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="openCustomer()"><i class="fas fa-user-plus"></i>Thêm khách hàng</button></div>
<form class="filter-bar"><div class="search-box"><i class="fas fa-search"></i><input name="q" value="<?= htmlspecialchars($keyword) ?>" placeholder="Tìm theo tên, số điện thoại, CCCD"></div><button class="filter-submit">Tìm kiếm</button><?php if($keyword!==''):?><a href="customers.php">Xóa lọc</a><?php endif;?></form>
<section class="panel table-panel"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Khách hàng</th><th>Liên hệ</th><th>CCCD</th><th>Địa chỉ</th><th>Lượt thuê</th><th class="text-end">Thao tác</th></tr></thead><tbody>
<?php if($data&&$data->num_rows):while($row=$data->fetch_assoc()):?><tr><td><strong><?= htmlspecialchars($row['fullname']) ?></strong><small class="d-block text-muted">Tạo ngày <?= date('d/m/Y',strtotime($row['created_at'])) ?></small></td><td><?= htmlspecialchars($row['phone']) ?><small class="d-block text-muted"><?= htmlspecialchars($row['email']?:'Chưa có email') ?></small></td><td><?= htmlspecialchars($row['identity_number']?:'Chưa cập nhật') ?></td><td><?= htmlspecialchars($row['address']?:'Chưa cập nhật') ?></td><td><span class="soft-badge"><?= number_format($row['rental_count']) ?> phiếu</span></td><td class="text-end"><button class="icon-btn" onclick='openCustomer(<?= json_encode($row,JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'><i class="fas fa-pen"></i></button><button class="icon-btn danger" onclick="removeCustomer(<?= $row['id'] ?>)"><i class="fas fa-trash"></i></button></td></tr><?php endwhile;else:?><tr><td colspan="6" class="empty">Không tìm thấy khách hàng.</td></tr><?php endif;?>
</tbody></table></div>
<?php if($pages>1):?><div class="pagination-wrap"><?php for($i=1;$i<=$pages;$i++):?><a class="<?= $i===$page?'active':'' ?>" href="?page=<?= $i ?>&q=<?= urlencode($keyword) ?>"><?= $i ?></a><?php endfor;?></div><?php endif;?>
</section>
<div class="modal fade" id="customerModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form method="post" class="modal-content"><input type="hidden" name="id" id="customer_id"><div class="modal-header"><h5 id="customer_title">Thêm khách hàng</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-6"><label>Họ và tên</label><input class="form-control" name="fullname" id="customer_fullname" required></div><div class="col-md-6"><label>Số điện thoại</label><input class="form-control" name="phone" id="customer_phone" required></div><div class="col-md-6"><label>Email</label><input type="email" class="form-control" name="email" id="customer_email"></div><div class="col-md-6"><label>Số CCCD</label><input class="form-control" name="identity_number" id="customer_identity"></div><div class="col-12"><label>Địa chỉ</label><input class="form-control" name="address" id="customer_address"></div><div class="col-12"><label>Ghi chú</label><textarea class="form-control" rows="3" name="note" id="customer_note"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button><button class="btn-primary-soft" name="save">Lưu khách hàng</button></div></form></div></div>
<script>
function openCustomer(data=null){
['id','fullname','phone','email','identity','address','note'].forEach(k=>{const el=document.getElementById('customer_'+k);if(el)el.value='';});
document.getElementById('customer_title').textContent=data?'Cập nhật khách hàng':'Thêm khách hàng';
if(data){document.getElementById('customer_id').value=data.id;document.getElementById('customer_fullname').value=data.fullname;document.getElementById('customer_phone').value=data.phone;document.getElementById('customer_email').value=data.email||'';document.getElementById('customer_identity').value=data.identity_number||'';document.getElementById('customer_address').value=data.address||'';document.getElementById('customer_note').value=data.note||'';new bootstrap.Modal(document.getElementById('customerModal')).show();}
}
function removeCustomer(id){Swal.fire({title:'Xóa khách hàng?',text:'Dữ liệu không thể khôi phục.',icon:'warning',showCancelButton:true,confirmButtonText:'Xóa',cancelButtonText:'Hủy',confirmButtonColor:'#d66c7a'}).then(r=>{if(r.isConfirmed)location.href='?delete='+id;});}
</script>
<?php include 'footer.php'; ?>