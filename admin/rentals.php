<?php
include 'header.php';
$conn->query("UPDATE rentals SET status='overdue' WHERE status='picked_up' AND return_date<CURDATE()");
$error='';
try{
if(isset($_POST['save_rental'])){
    $id=(int)($_POST['id']??0);
    $customerId=(int)($_POST['customer_id']??0);
    $receiveDate=$_POST['receive_date']??'';
    $returnDate=$_POST['return_date']??'';
    $discount=normalize_price($_POST['discount']??0);
    $note=trim($_POST['note']??'');
    $costumeIds=$_POST['costume_id']??[];
    $quantities=$_POST['item_quantity']??[];
    $sizes=$_POST['item_size']??[];
    $conditions=$_POST['condition_out']??[];
    if($customerId<=0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$receiveDate)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$returnDate))throw new Exception('Vui lòng chọn khách hàng và ngày thuê');
    if(strtotime($returnDate)<strtotime($receiveDate))throw new Exception('Ngày trả phải bằng hoặc sau ngày nhận');
    if(!$costumeIds)throw new Exception('Vui lòng chọn ít nhất một trang phục');
    $days=(int)((strtotime($returnDate)-strtotime($receiveDate))/86400)+1;
    $items=[];$rentalTotal=0;$depositTotal=0;$seen=[];
    foreach($costumeIds as $index=>$costumeIdRaw){
        $costumeId=(int)$costumeIdRaw;$qty=max(1,(int)($quantities[$index]??1));
        if($costumeId<=0||isset($seen[$costumeId]))continue;
        $seen[$costumeId]=true;
        $stmt=$conn->prepare("SELECT id,name,size,rental_price,deposit,status FROM costumes WHERE id=?");$stmt->bind_param('i',$costumeId);$stmt->execute();$costume=$stmt->get_result()->fetch_assoc();
        if(!$costume)throw new Exception('Trang phục không tồn tại');
        $available=available_quantity($conn,$costumeId,$receiveDate,$returnDate,$id);
        if($available<$qty)throw new Exception($costume['name'].' chỉ còn '.$available.' sản phẩm trống trong thời gian đã chọn');
        $unitPrice=(float)$costume['rental_price'];$subtotal=$unitPrice*$qty*$days;$itemDeposit=(float)$costume['deposit']*$qty;
        $items[]=['costume_id'=>$costumeId,'quantity'=>$qty,'size'=>trim($sizes[$index]??$costume['size']),'unit_price'=>$unitPrice,'days'=>$days,'subtotal'=>$subtotal,'deposit'=>$itemDeposit,'condition_out'=>trim($conditions[$index]??'Tốt')];
        $rentalTotal+=$subtotal;$depositTotal+=$itemDeposit;
    }
    if(!$items)throw new Exception('Danh sách trang phục không hợp lệ');
    if($discount>$rentalTotal)throw new Exception('Giảm giá không được lớn hơn tiền thuê');
    $final=$rentalTotal-$discount+$depositTotal;
    $conn->begin_transaction();
    if($id>0){
        $stmt=$conn->prepare("SELECT status FROM rentals WHERE id=? FOR UPDATE");$stmt->bind_param('i',$id);$stmt->execute();$old=$stmt->get_result()->fetch_assoc();
        if(!$old||$old['status']!=='reserved')throw new Exception('Chỉ được sửa phiếu đang ở trạng thái đã đặt');
        $stmt=$conn->prepare("UPDATE rentals SET customer_id=?,receive_date=?,return_date=?,rental_amount=?,deposit_amount=?,discount=?,final_amount=?,note=? WHERE id=?");
        $stmt->bind_param('issddddsi',$customerId,$receiveDate,$returnDate,$rentalTotal,$depositTotal,$discount,$final,$note,$id);$stmt->execute();
        $stmt=$conn->prepare("DELETE FROM rental_items WHERE rental_id=?");$stmt->bind_param('i',$id);$stmt->execute();
        $rentalId=$id;
    }else{
        $code=generate_rental_code($conn);
        $stmt=$conn->prepare("INSERT INTO rentals(rental_code,customer_id,receive_date,return_date,rental_amount,deposit_amount,discount,final_amount,note,status,payment_status) VALUES(?,?,?,?,?,?,?,?,?,'reserved','unpaid')");
        $stmt->bind_param('sissdddds',$code,$customerId,$receiveDate,$returnDate,$rentalTotal,$depositTotal,$discount,$final,$note);$stmt->execute();$rentalId=$conn->insert_id;
    }
    $stmt=$conn->prepare("INSERT INTO rental_items(rental_id,costume_id,quantity,selected_size,unit_price,rental_days,subtotal,deposit_amount,condition_out) VALUES(?,?,?,?,?,?,?,?,?)");
    foreach($items as $item){$stmt->bind_param('iiisdddds',$rentalId,$item['costume_id'],$item['quantity'],$item['size'],$item['unit_price'],$item['days'],$item['subtotal'],$item['deposit'],$item['condition_out']);$stmt->execute();}
    $conn->commit();header('Location: rentals.php');exit;
}
if(isset($_POST['status_action'])){
    $id=(int)($_POST['rental_id']??0);$action=$_POST['status_action'];
    $stmt=$conn->prepare("SELECT status FROM rentals WHERE id=?");$stmt->bind_param('i',$id);$stmt->execute();$rental=$stmt->get_result()->fetch_assoc();
    if(!$rental)throw new Exception('Không tìm thấy phiếu thuê');
    $allowed=['pickup'=>['reserved','picked_up'],'return'=>['picked_up','returned'],'cancel'=>['reserved','cancelled']];
    if(!isset($allowed[$action])||$rental['status']!==$allowed[$action][0])throw new Exception('Không thể chuyển trạng thái phiếu thuê');
    $newStatus=$allowed[$action][1];
    $stmt=$conn->prepare("UPDATE rentals SET status=? WHERE id=?");$stmt->bind_param('si',$newStatus,$id);$stmt->execute();
    header('Location: rentals.php');exit;
}
if(isset($_GET['delete'])){
    $id=(int)$_GET['delete'];
    $stmt=$conn->prepare("SELECT r.status,COUNT(p.id) payment_count FROM rentals r LEFT JOIN payments p ON p.rental_id=r.id WHERE r.id=? GROUP BY r.id");$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
    if(!$row||$row['status']!=='cancelled'||(int)$row['payment_count']>0)throw new Exception('Chỉ xóa được phiếu đã hủy và chưa có thanh toán');
    $stmt=$conn->prepare("DELETE FROM rentals WHERE id=?");$stmt->bind_param('i',$id);$stmt->execute();header('Location: rentals.php');exit;
}
}catch(Throwable $e){if($conn->errno)$conn->rollback();$error=$e->getMessage();}
$page=max(1,(int)($_GET['page']??1));$limit=10;$offset=($page-1)*$limit;$status=$_GET['status']??'';$keyword=trim($_GET['q']??'');
$conditions=[];$types='';$values=[];
if(in_array($status,['reserved','picked_up','returned','cancelled','overdue'],true)){$conditions[]='r.status=?';$types.='s';$values[]=$status;}
if($keyword!==''){$conditions[]='(r.rental_code LIKE ? OR c.fullname LIKE ? OR c.phone LIKE ?)';$like='%'.$keyword.'%';$types.='sss';array_push($values,$like,$like,$like);}
$where=$conditions?'WHERE '.implode(' AND ',$conditions):'';
$stmt=$conn->prepare("SELECT r.*,c.fullname,c.phone FROM rentals r JOIN customers c ON c.id=r.customer_id $where ORDER BY r.id DESC LIMIT ?,?");$types2=$types.'ii';$values2=array_merge($values,[$offset,$limit]);$stmt->bind_param($types2,...$values2);$stmt->execute();$data=$stmt->get_result();
$stmt=$conn->prepare("SELECT COUNT(*) total FROM rentals r JOIN customers c ON c.id=r.customer_id $where");if($types)$stmt->bind_param($types,...$values);$stmt->execute();$total=(int)$stmt->get_result()->fetch_assoc()['total'];$pages=max(1,(int)ceil($total/$limit));
$customers=$conn->query("SELECT id,fullname,phone FROM customers ORDER BY fullname");
$costumes=$conn->query("SELECT id,code,name,size,rental_price,deposit,quantity FROM costumes WHERE status='available' ORDER BY name");
$costumeArray=[];while($co=$costumes->fetch_assoc())$costumeArray[]=$co;
$openAdd=isset($_GET['add']);
?>
<?php if($error):?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif;?>
<div class="page-head"><div><h2>Phiếu thuê trang phục</h2><p>Quản lý lịch nhận, ngày trả, tiền thuê và tiền cọc.</p></div><button class="btn-primary-soft" data-bs-toggle="modal" data-bs-target="#rentalModal" onclick="openRental()"><i class="fas fa-plus"></i>Tạo phiếu thuê</button></div>
<form class="filter-bar"><div class="search-box"><i class="fas fa-search"></i><input name="q" value="<?= htmlspecialchars($keyword) ?>" placeholder="Tìm mã phiếu, tên hoặc số điện thoại"></div><select name="status"><option value="">Tất cả trạng thái</option><?php foreach(['reserved','picked_up','returned','overdue','cancelled'] as $s):?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= rental_status_label($s) ?></option><?php endforeach;?></select><button class="filter-submit">Lọc</button><a href="rentals.php">Đặt lại</a></form>
<section class="panel table-panel"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Mã phiếu</th><th>Khách hàng</th><th>Thời gian thuê</th><th>Tiền thuê</th><th>Tổng cần thu</th><th>Thanh toán</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead><tbody>
<?php if($data&&$data->num_rows):while($row=$data->fetch_assoc()):?>
<tr><td><strong><?= htmlspecialchars($row['rental_code']) ?></strong><small class="d-block text-muted"><?= date('d/m/Y H:i',strtotime($row['created_at'])) ?></small></td><td><?= htmlspecialchars($row['fullname']) ?><small class="d-block text-muted"><?= htmlspecialchars($row['phone']) ?></small></td><td><?= date('d/m/Y',strtotime($row['receive_date'])) ?><span class="date-arrow"><i class="fas fa-arrow-right"></i></span><?= date('d/m/Y',strtotime($row['return_date'])) ?></td><td><?= format_price($row['rental_amount']) ?>đ</td><td><strong><?= format_price($row['final_amount']) ?>đ</strong></td><td><span class="payment payment-<?= $row['payment_status'] ?>"><?= payment_status_label($row['payment_status']) ?></span><small class="d-block text-muted"><?= format_price($row['paid_amount']) ?>đ</small></td><td><span class="status status-<?= $row['status'] ?>"><?= rental_status_label($row['status']) ?></span></td><td class="text-end"><button class="icon-btn" onclick="viewRental(<?= $row['id'] ?>)" title="Chi tiết"><i class="fas fa-eye"></i></button><?php if($row['status']==='reserved'):?><button class="icon-btn" onclick="editRental(<?= $row['id'] ?>)" title="Sửa"><i class="fas fa-pen"></i></button><button class="icon-btn success" onclick="changeStatus(<?= $row['id'] ?>,'pickup','Xác nhận khách đã nhận trang phục?')" title="Nhận đồ"><i class="fas fa-check"></i></button><button class="icon-btn danger" onclick="changeStatus(<?= $row['id'] ?>,'cancel','Hủy phiếu thuê này?')" title="Hủy"><i class="fas fa-ban"></i></button><?php elseif(in_array($row['status'],['picked_up','overdue'],true)):?><button class="icon-btn success" onclick="changeStatus(<?= $row['id'] ?>,'return','Xác nhận khách đã trả đủ trang phục?')" title="Trả đồ"><i class="fas fa-rotate-left"></i></button><?php elseif($row['status']==='cancelled'):?><button class="icon-btn danger" onclick="deleteRental(<?= $row['id'] ?>)" title="Xóa"><i class="fas fa-trash"></i></button><?php endif;?><a class="icon-btn" href="print_rental.php?id=<?= $row['id'] ?>" target="_blank" title="In phiếu"><i class="fas fa-print"></i></a></td></tr>
<?php endwhile;else:?><tr><td colspan="8" class="empty">Không tìm thấy phiếu thuê.</td></tr><?php endif;?>
</tbody></table></div><?php if($pages>1):?><div class="pagination-wrap"><?php for($i=1;$i<=$pages;$i++):?><a class="<?= $i===$page?'active':'' ?>" href="?page=<?= $i ?>&q=<?= urlencode($keyword) ?>&status=<?= urlencode($status) ?>"><?= $i ?></a><?php endfor;?></div><?php endif;?></section>
<div class="modal fade" id="rentalModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><form method="post" class="modal-content" id="rentalForm"><input type="hidden" name="id" id="rental_id"><div class="modal-header"><h5 id="rental_title">Tạo phiếu thuê</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<div class="rental-form-grid"><section><h6>Thông tin phiếu thuê</h6><div class="row g-3"><div class="col-md-6"><label>Khách hàng</label><select class="form-select" name="customer_id" id="rental_customer" required><option value="">Chọn khách hàng</option><?php while($c=$customers->fetch_assoc()):?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['fullname']) ?> · <?= htmlspecialchars($c['phone']) ?></option><?php endwhile;?></select></div><div class="col-md-3"><label>Ngày nhận</label><input type="date" class="form-control" name="receive_date" id="rental_receive" min="<?= date('Y-m-d') ?>" required></div><div class="col-md-3"><label>Ngày trả</label><input type="date" class="form-control" name="return_date" id="rental_return" min="<?= date('Y-m-d') ?>" required></div><div class="col-md-4"><label>Giảm giá</label><input class="form-control money-input" inputmode="numeric" name="discount" id="rental_discount" value="0"></div><div class="col-md-8"><label>Ghi chú</label><input class="form-control" name="note" id="rental_note"></div></div></section>
<section class="rental-items-section"><div class="rental-items-head"><div><h6>Trang phục thuê</h6><span>Giá thuê được tính theo số ngày</span></div><button type="button" onclick="addRentalItem()"><i class="fas fa-plus"></i>Thêm dòng</button></div><div id="rentalItems"></div><div class="rental-summary"><span>Tạm tính được cập nhật khi chọn trang phục và ngày thuê.</span><strong id="rentalEstimate">0đ</strong></div></section></div>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button><button class="btn-primary-soft" name="save_rental">Lưu phiếu thuê</button></div></form></div></div>
<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5>Chi tiết phiếu thuê</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="detailContent"><div class="loading"><i class="fas fa-spinner fa-spin"></i>Đang tải...</div></div></div></div></div>
<form method="post" id="statusForm"><input type="hidden" name="rental_id" id="status_rental_id"><input type="hidden" name="status_action" id="status_action"></form>
<script>
const costumes=<?= json_encode($costumeArray,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
let itemIndex=0;
function costumeOptions(selected=''){return '<option value="">Chọn trang phục</option>'+costumes.map(c=>`<option value="${c.id}" ${String(selected)===String(c.id)?'selected':''}>${c.code} · ${c.name} · Size ${c.size}</option>`).join('');}
function addRentalItem(item=null){
const box=document.getElementById('rentalItems');const row=document.createElement('div');row.className='rental-item-row';row.dataset.index=itemIndex++;
row.innerHTML=`<div><label>Trang phục</label><select name="costume_id[]" class="form-select costume-select" required>${costumeOptions(item?item.costume_id:'')}</select></div><div><label>Size</label><input name="item_size[]" class="form-control item-size" value="${item?escapeHtml(item.selected_size||''):''}" required></div><div><label>Số lượng</label><input type="number" min="1" name="item_quantity[]" class="form-control item-qty" value="${item?item.quantity:1}" required></div><div><label>Tình trạng giao</label><input name="condition_out[]" class="form-control" value="${item?escapeHtml(item.condition_out||'Tốt'):'Tốt'}"></div><button type="button" class="remove-row"><i class="fas fa-xmark"></i></button>`;
row.querySelector('.remove-row').onclick=()=>{row.remove();calculateEstimate();};
row.querySelector('.costume-select').addEventListener('change',e=>{const c=costumes.find(x=>String(x.id)===e.target.value);if(c&&!row.querySelector('.item-size').value)row.querySelector('.item-size').value=c.size;calculateEstimate();});
row.querySelector('.item-qty').addEventListener('input',calculateEstimate);box.appendChild(row);calculateEstimate();
}
function calculateEstimate(){const start=document.getElementById('rental_receive').value,end=document.getElementById('rental_return').value;let days=1;if(start&&end){days=Math.max(1,Math.floor((new Date(end)-new Date(start))/86400000)+1);}let total=0,deposit=0;document.querySelectorAll('.rental-item-row').forEach(row=>{const c=costumes.find(x=>String(x.id)===row.querySelector('.costume-select').value);const qty=Number(row.querySelector('.item-qty').value)||0;if(c){total+=Number(c.rental_price)*qty*days;deposit+=Number(c.deposit)*qty;}});const discount=Number(document.getElementById('rental_discount').value.replace(/\D/g,''))||0;document.getElementById('rentalEstimate').textContent=(Math.max(0,total-discount)+deposit).toLocaleString('en-US')+'đ';}
function escapeHtml(v){return String(v).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
function openRental(data=null){
document.getElementById('rental_id').value='';document.getElementById('rental_customer').value='';document.getElementById('rental_receive').value='';document.getElementById('rental_return').value='';document.getElementById('rental_discount').value='0';document.getElementById('rental_note').value='';document.getElementById('rentalItems').innerHTML='';document.getElementById('rental_title').textContent='Tạo phiếu thuê';itemIndex=0;addRentalItem();
}
async function editRental(id){const res=await fetch('ajax_get_rental.php?id='+id);const data=await res.json();if(!data.success){Swal.fire('Lỗi',data.message,'error');return;}openRental();const r=data.rental;document.getElementById('rental_id').value=r.id;document.getElementById('rental_customer').value=r.customer_id;document.getElementById('rental_receive').value=r.receive_date;document.getElementById('rental_return').value=r.return_date;document.getElementById('rental_discount').value=Number(r.discount).toLocaleString('en-US');document.getElementById('rental_note').value=r.note||'';document.getElementById('rentalItems').innerHTML='';data.items.forEach(addRentalItem);document.getElementById('rental_title').textContent='Cập nhật phiếu thuê';calculateEstimate();new bootstrap.Modal(document.getElementById('rentalModal')).show();}
async function viewRental(id){const modal=new bootstrap.Modal(document.getElementById('detailModal'));modal.show();document.getElementById('detailContent').innerHTML='<div class="loading"><i class="fas fa-spinner fa-spin"></i>Đang tải...</div>';const html=await fetch('ajax_get_rental.php?id='+id+'&html=1').then(r=>r.text());document.getElementById('detailContent').innerHTML=html;}
function changeStatus(id,action,message){Swal.fire({title:message,icon:'question',showCancelButton:true,confirmButtonText:'Xác nhận',cancelButtonText:'Hủy'}).then(r=>{if(r.isConfirmed){document.getElementById('status_rental_id').value=id;document.getElementById('status_action').value=action;document.getElementById('statusForm').submit();}});}
function deleteRental(id){Swal.fire({title:'Xóa phiếu đã hủy?',icon:'warning',showCancelButton:true,confirmButtonText:'Xóa',cancelButtonText:'Đóng',confirmButtonColor:'#d66c7a'}).then(r=>{if(r.isConfirmed)location.href='?delete='+id;});}
document.getElementById('rental_receive').addEventListener('change',calculateEstimate);document.getElementById('rental_return').addEventListener('change',calculateEstimate);document.getElementById('rental_discount').addEventListener('input',calculateEstimate);
<?php if($openAdd):?>document.addEventListener('DOMContentLoaded',()=>{openRental();new bootstrap.Modal(document.getElementById('rentalModal')).show();});<?php endif;?>
</script>
<?php include 'footer.php'; ?>