<?php
include 'header.php';
function save_costume_image(array $file,string $current=''):string{
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return $current;
    if(($file['error']??0)!==UPLOAD_ERR_OK)throw new Exception('Tải ảnh thất bại');
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/svg+xml'=>'svg'];
    $mime=mime_content_type($file['tmp_name']);
    if(!isset($allowed[$mime]))throw new Exception('Ảnh phải là JPG, PNG, WEBP hoặc SVG');
    if(($file['size']??0)>5*1024*1024)throw new Exception('Ảnh không được vượt quá 5MB');
    $name='costume-'.date('YmdHis').'-'.bin2hex(random_bytes(3)).'.'.$allowed[$mime];
    if(!move_uploaded_file($file['tmp_name'],'../uploads/'.$name))throw new Exception('Không thể lưu ảnh');
    if($current&&is_file('../uploads/'.$current)&&strpos($current,'sample-')!==0)@unlink('../uploads/'.$current);
    return $name;
}
$error='';
try{
if(isset($_POST['save'])){
    $id=(int)($_POST['id']??0);
    $code=strtoupper(trim($_POST['code']??''));
    $name=trim($_POST['name']??'');
    $categoryId=(int)($_POST['category_id']??0);
    $size=trim($_POST['size']??'');
    $color=trim($_POST['color']??'');
    $rentalPrice=normalize_price($_POST['rental_price']??0);
    $deposit=normalize_price($_POST['deposit']??0);
    $quantity=max(0,(int)($_POST['quantity']??0));
    $status=$_POST['status']??'available';
    $conditionNote=trim($_POST['condition_note']??'');
    $description=$_POST['description']??'';
    if($code===''||$name===''||$categoryId<=0||$size===''||$rentalPrice<=0||$quantity<=0)throw new Exception('Vui lòng nhập đầy đủ thông tin bắt buộc');
    if(!in_array($status,['available','maintenance','retired'],true))throw new Exception('Trạng thái không hợp lệ');
    $check=$conn->prepare("SELECT id FROM costumes WHERE code=? AND id<>?");$check->bind_param('si',$code,$id);$check->execute();
    if($check->get_result()->num_rows)throw new Exception('Mã trang phục đã tồn tại');
    $current='';
    if($id>0){$stmt=$conn->prepare("SELECT image FROM costumes WHERE id=?");$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();if(!$row)throw new Exception('Không tìm thấy trang phục');$current=$row['image']??'';}
    $image=save_costume_image($_FILES['image']??[],$current);
    if($image==='')throw new Exception('Vui lòng chọn ảnh trang phục');
    if($id>0){$stmt=$conn->prepare("UPDATE costumes SET code=?,name=?,category_id=?,size=?,color=?,rental_price=?,deposit=?,quantity=?,status=?,condition_note=?,description=?,image=? WHERE id=?");$stmt->bind_param('ssissddissssi',$code,$name,$categoryId,$size,$color,$rentalPrice,$deposit,$quantity,$status,$conditionNote,$description,$image,$id);}
    else{$stmt=$conn->prepare("INSERT INTO costumes(code,name,category_id,size,color,rental_price,deposit,quantity,status,condition_note,description,image) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");$stmt->bind_param('ssissddissss',$code,$name,$categoryId,$size,$color,$rentalPrice,$deposit,$quantity,$status,$conditionNote,$description,$image);}
    $stmt->execute();
    header('Location: costumes.php');exit;
}
if(isset($_GET['delete'])){
    $id=(int)$_GET['delete'];
    $stmt=$conn->prepare("SELECT COUNT(*) total FROM rental_items WHERE costume_id=?");$stmt->bind_param('i',$id);$stmt->execute();
    if((int)$stmt->get_result()->fetch_assoc()['total']>0)throw new Exception('Trang phục đã phát sinh phiếu thuê, không thể xóa');
    $stmt=$conn->prepare("SELECT image FROM costumes WHERE id=?");$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
    $stmt=$conn->prepare("DELETE FROM costumes WHERE id=?");$stmt->bind_param('i',$id);$stmt->execute();
    if($row&&$row['image']&&strpos($row['image'],'sample-')!==0&&is_file('../uploads/'.$row['image']))@unlink('../uploads/'.$row['image']);
    header('Location: costumes.php');exit;
}
}catch(Throwable $e){$error=$e->getMessage();}
$page=max(1,(int)($_GET['page']??1));$limit=10;$offset=($page-1)*$limit;$keyword=trim($_GET['q']??'');$categoryFilter=(int)($_GET['category']??0);$statusFilter=$_GET['status']??'';
$conditions=[];$types='';$values=[];
if($keyword!==''){$conditions[]="(co.name LIKE ? OR co.code LIKE ? OR co.size LIKE ?)";$like='%'.$keyword.'%';$types.='sss';array_push($values,$like,$like,$like);}
if($categoryFilter>0){$conditions[]='co.category_id=?';$types.='i';$values[]=$categoryFilter;}
if(in_array($statusFilter,['available','maintenance','retired'],true)){$conditions[]='co.status=?';$types.='s';$values[]=$statusFilter;}
$where=$conditions?'WHERE '.implode(' AND ',$conditions):'';
$sql="SELECT co.*,c.name category_name FROM costumes co LEFT JOIN categories c ON c.id=co.category_id $where ORDER BY co.id DESC LIMIT ?,?";
$stmt=$conn->prepare($sql);$types2=$types.'ii';$values2=array_merge($values,[$offset,$limit]);$stmt->bind_param($types2,...$values2);$stmt->execute();$data=$stmt->get_result();
$stmt=$conn->prepare("SELECT COUNT(*) total FROM costumes co $where");if($types)$stmt->bind_param($types,...$values);$stmt->execute();$total=(int)$stmt->get_result()->fetch_assoc()['total'];$pages=max(1,(int)ceil($total/$limit));
$categories=$conn->query("SELECT * FROM categories ORDER BY name");
$categoriesModal=$conn->query("SELECT * FROM categories ORDER BY name");
?>
<script src="https://cdn.ckeditor.com/4.22.1/full-all/ckeditor.js"></script>
<?php if($error):?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif;?>
<div class="page-head"><div><h2>Trang phục cho thuê</h2><p>Quản lý mã, size, giá thuê, tiền cọc và tình trạng.</p></div><button class="btn-primary-soft" data-bs-toggle="modal" data-bs-target="#costumeModal" onclick="openCostume()"><i class="fas fa-plus"></i>Thêm trang phục</button></div>
<form class="filter-bar">
<div class="search-box"><i class="fas fa-search"></i><input name="q" value="<?= htmlspecialchars($keyword) ?>" placeholder="Tìm tên, mã hoặc size"></div>
<select name="category"><option value="0">Tất cả danh mục</option><?php while($c=$categories->fetch_assoc()):?><option value="<?= $c['id'] ?>" <?= $categoryFilter===$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option><?php endwhile;?></select>
<select name="status"><option value="">Tất cả trạng thái</option><option value="available" <?= $statusFilter==='available'?'selected':'' ?>>Sẵn sàng</option><option value="maintenance" <?= $statusFilter==='maintenance'?'selected':'' ?>>Bảo dưỡng</option><option value="retired" <?= $statusFilter==='retired'?'selected':'' ?>>Ngừng cho thuê</option></select>
<button class="filter-submit">Lọc</button><a href="costumes.php">Đặt lại</a>
</form>
<section class="panel table-panel"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Trang phục</th><th>Danh mục</th><th>Size</th><th>Giá thuê</th><th>Tiền cọc</th><th>Số lượng</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead><tbody>
<?php if($data&&$data->num_rows):while($row=$data->fetch_assoc()):?>
<tr><td><div class="costume-cell"><img src="../uploads/<?= htmlspecialchars($row['image']) ?>" alt=""><div><strong><?= htmlspecialchars($row['name']) ?></strong><span><?= htmlspecialchars($row['code']) ?> · <?= htmlspecialchars($row['color']?:'Chưa có màu') ?></span></div></div></td><td><?= htmlspecialchars($row['category_name']??'Chưa phân loại') ?></td><td><span class="size-badge"><?= htmlspecialchars($row['size']) ?></span></td><td><strong><?= format_price($row['rental_price']) ?>đ</strong></td><td><?= format_price($row['deposit']) ?>đ</td><td><?= number_format($row['quantity']) ?></td><td><span class="status costume-<?= $row['status'] ?>"><?= costume_status_label($row['status']) ?></span></td><td class="text-end"><button class="icon-btn" onclick='openCostume(<?= json_encode($row,JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'><i class="fas fa-pen"></i></button><button class="icon-btn danger" onclick="removeCostume(<?= $row['id'] ?>)"><i class="fas fa-trash"></i></button></td></tr>
<?php endwhile;else:?><tr><td colspan="8" class="empty">Không tìm thấy trang phục.</td></tr><?php endif;?>
</tbody></table></div>
<?php if($pages>1):?><div class="pagination-wrap"><?php for($i=1;$i<=$pages;$i++):?><a class="<?= $i===$page?'active':'' ?>" href="?page=<?= $i ?>&q=<?= urlencode($keyword) ?>&category=<?= $categoryFilter ?>&status=<?= urlencode($statusFilter) ?>"><?= $i ?></a><?php endfor;?></div><?php endif;?>
</section>
<div class="modal fade" id="costumeModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><form method="post" enctype="multipart/form-data" class="modal-content"><input type="hidden" name="id" id="costume_id"><div class="modal-header"><h5 id="costume_title">Thêm trang phục</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3">
<div class="col-md-3"><label>Mã trang phục</label><input class="form-control" name="code" id="costume_code" required></div>
<div class="col-md-6"><label>Tên trang phục</label><input class="form-control" name="name" id="costume_name" required></div>
<div class="col-md-3"><label>Danh mục</label><select class="form-select" name="category_id" id="costume_category" required><?php while($c=$categoriesModal->fetch_assoc()):?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endwhile;?></select></div>
<div class="col-md-3"><label>Kích thước</label><input class="form-control" name="size" id="costume_size" placeholder="S, M, L, XL..." required></div>
<div class="col-md-3"><label>Màu sắc</label><input class="form-control" name="color" id="costume_color"></div>
<div class="col-md-3"><label>Giá thuê</label><input class="form-control money-input" inputmode="numeric" name="rental_price" id="costume_rental_price" required></div>
<div class="col-md-3"><label>Tiền cọc</label><input class="form-control money-input" inputmode="numeric" name="deposit" id="costume_deposit"></div>
<div class="col-md-3"><label>Số lượng</label><input type="number" min="1" class="form-control" name="quantity" id="costume_quantity" required></div>
<div class="col-md-3"><label>Trạng thái</label><select class="form-select" name="status" id="costume_status"><option value="available">Sẵn sàng</option><option value="maintenance">Đang bảo dưỡng</option><option value="retired">Ngừng cho thuê</option></select></div>
<div class="col-md-6"><label>Ảnh trang phục</label><input type="file" class="form-control" name="image" accept="image/*,.svg" id="costume_image"></div>
<div class="col-12"><label>Tình trạng hiện tại</label><input class="form-control" name="condition_note" id="costume_condition_note" placeholder="Ví dụ: Mới 95%, đã vệ sinh"></div>
<div class="col-12"><label>Mô tả chi tiết</label><textarea name="description" id="costume_description"></textarea></div>
</div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button><button class="btn-primary-soft" name="save">Lưu trang phục</button></div></form></div></div>
<script>
const editorConfig={height:220,filebrowserUploadUrl:'upload_ckeditor.php',filebrowserUploadMethod:'form',extraPlugins:'uploadimage,iframe',removePlugins:'easyimage,cloudservices'};
CKEDITOR.replace('costume_description',editorConfig);
function openCostume(data=null){
const ids=['id','code','name','size','color','rental_price','deposit','quantity','condition_note'];
ids.forEach(k=>{const el=document.getElementById('costume_'+k);if(el)el.value='';});
document.getElementById('costume_status').value='available';
document.getElementById('costume_title').textContent=data?'Cập nhật trang phục':'Thêm trang phục';
CKEDITOR.instances.costume_description.setData(data?data.description||'':'');
if(data){
document.getElementById('costume_id').value=data.id;document.getElementById('costume_code').value=data.code;document.getElementById('costume_name').value=data.name;document.getElementById('costume_category').value=data.category_id;document.getElementById('costume_size').value=data.size;document.getElementById('costume_color').value=data.color||'';document.getElementById('costume_rental_price').value=Number(data.rental_price).toLocaleString('en-US');document.getElementById('costume_deposit').value=Number(data.deposit).toLocaleString('en-US');document.getElementById('costume_quantity').value=data.quantity;document.getElementById('costume_status').value=data.status;document.getElementById('costume_condition_note').value=data.condition_note||'';new bootstrap.Modal(document.getElementById('costumeModal')).show();
}
}
function removeCostume(id){Swal.fire({title:'Xóa trang phục?',text:'Dữ liệu không thể khôi phục.',icon:'warning',showCancelButton:true,confirmButtonText:'Xóa',cancelButtonText:'Hủy',confirmButtonColor:'#d66c7a'}).then(r=>{if(r.isConfirmed)location.href='?delete='+id;});}
</script>
<?php include 'footer.php'; ?>