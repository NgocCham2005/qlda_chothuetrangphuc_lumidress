<?php
include 'header.php';
$error='';
try{
if(isset($_POST['save'])){
    $id=(int)($_POST['id']??0);
    $name=trim($_POST['name']??'');
    $description=trim($_POST['description']??'');
    if($name==='')throw new Exception('Vui lòng nhập tên danh mục');
    if($id>0){$stmt=$conn->prepare("UPDATE categories SET name=?,description=? WHERE id=?");$stmt->bind_param('ssi',$name,$description,$id);}
    else{$stmt=$conn->prepare("INSERT INTO categories(name,description) VALUES(?,?)");$stmt->bind_param('ss',$name,$description);}
    $stmt->execute();
    header('Location: categories.php');exit;
}
if(isset($_GET['delete'])){
    $id=(int)$_GET['delete'];
    $stmt=$conn->prepare("SELECT COUNT(*) total FROM costumes WHERE category_id=?");$stmt->bind_param('i',$id);$stmt->execute();
    if((int)$stmt->get_result()->fetch_assoc()['total']>0)throw new Exception('Danh mục đang có trang phục, không thể xóa');
    $stmt=$conn->prepare("DELETE FROM categories WHERE id=?");$stmt->bind_param('i',$id);$stmt->execute();
    header('Location: categories.php');exit;
}
}catch(Throwable $e){$error=$e->getMessage();}
$data=$conn->query("SELECT c.*,COUNT(co.id) total_costumes FROM categories c LEFT JOIN costumes co ON co.category_id=c.id GROUP BY c.id ORDER BY c.id DESC");
?>
<?php if($error):?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif;?>
<div class="page-head"><div><h2>Danh mục trang phục</h2><p>Phân loại váy cưới, vest, áo dài và phụ kiện.</p></div><button class="btn-primary-soft" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openCategory()"><i class="fas fa-plus"></i>Thêm danh mục</button></div>
<section class="panel table-panel">
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Tên danh mục</th><th>Mô tả</th><th>Số trang phục</th><th class="text-end">Thao tác</th></tr></thead><tbody>
<?php if($data&&$data->num_rows):while($row=$data->fetch_assoc()):?>
<tr><td><strong><?= htmlspecialchars($row['name']) ?></strong></td><td><?= htmlspecialchars($row['description']?:'Chưa có mô tả') ?></td><td><span class="soft-badge"><?= number_format($row['total_costumes']) ?> mẫu</span></td><td class="text-end"><button class="icon-btn" onclick='openCategory(<?= json_encode($row,JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'><i class="fas fa-pen"></i></button><button class="icon-btn danger" onclick="removeCategory(<?= $row['id'] ?>)"><i class="fas fa-trash"></i></button></td></tr>
<?php endwhile;else:?><tr><td colspan="4" class="empty">Chưa có danh mục.</td></tr><?php endif;?>
</tbody></table></div></section>
<div class="modal fade" id="categoryModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><input type="hidden" name="id" id="category_id"><div class="modal-header"><h5 id="category_title">Thêm danh mục</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><label>Tên danh mục</label><input class="form-control" name="name" id="category_name" required><label>Mô tả</label><textarea class="form-control" rows="4" name="description" id="category_description"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button><button class="btn-primary-soft" name="save">Lưu danh mục</button></div></form></div></div>
<script>
function openCategory(data=null){
document.getElementById('category_id').value=data?data.id:'';
document.getElementById('category_name').value=data?data.name:'';
document.getElementById('category_description').value=data?data.description||'':'';
document.getElementById('category_title').textContent=data?'Cập nhật danh mục':'Thêm danh mục';
if(data)new bootstrap.Modal(document.getElementById('categoryModal')).show();
}
function removeCategory(id){Swal.fire({title:'Xóa danh mục?',text:'Dữ liệu không thể khôi phục.',icon:'warning',showCancelButton:true,confirmButtonText:'Xóa',cancelButtonText:'Hủy',confirmButtonColor:'#d66c7a'}).then(r=>{if(r.isConfirmed)location.href='?delete='+id;});}
</script>
<?php include 'footer.php'; ?>