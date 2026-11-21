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

$keyword=trim($_GET['q']??'');
$filter=trim($_GET['filter']??''); // 'has_costumes', 'no_costumes', or ''
$page=max(1,(int)($_GET['page']??1));
$limit=10;

// Xây dựng điều kiện WHERE / HAVING
$whereSql=" WHERE 1=1 ";
$params=[];
$types='';

if($keyword!==''){
    $whereSql.=" AND (c.name LIKE ? OR c.description LIKE ?) ";
    $paramLike="%{$keyword}%";
    $params[]=$paramLike;
    $params[]=$paramLike;
    $types.='ss';
}

$havingSql="";
if($filter==='has_costumes'){
    $havingSql=" HAVING total_costumes > 0 ";
}elseif($filter==='no_costumes'){
    $havingSql=" HAVING total_costumes = 0 ";
}

// Đếm tổng số bản ghi (dùng subquery để xử lý GROUP BY & HAVING)
$countSql="SELECT COUNT(*) as total_rows FROM (
    SELECT c.id, COUNT(co.id) total_costumes 
    FROM categories c 
    LEFT JOIN costumes co ON co.category_id=c.id 
    {$whereSql} 
    GROUP BY c.id 
    {$havingSql}
) as tbl";

$stmtCount=$conn->prepare($countSql);
if(!empty($params)){
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$totalRows=(int)$stmtCount->get_result()->fetch_assoc()['total_rows'];
$pages=ceil($totalRows/$limit);
if($page > $pages && $pages > 0) $page = $pages;
$offset=($page-1)*$limit;

// Lấy danh sách danh mục theo phân trang
$querySql="SELECT c.*, COUNT(co.id) total_costumes 
    FROM categories c 
    LEFT JOIN costumes co ON co.category_id=c.id 
    {$whereSql} 
    GROUP BY c.id 
    {$havingSql} 
    ORDER BY c.id DESC 
    LIMIT ?, ?";

$stmtQuery=$conn->prepare($querySql);
$queryParams=$params;
$queryTypes=$types . 'ii';
$queryParams[]=$offset;
$queryParams[]=$limit;

$stmtQuery->bind_param($queryTypes, ...$queryParams);
$stmtQuery->execute();
$data=$stmtQuery->get_result();
?>
<?php if($error):?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif;?>
<div class="page-head"><div><h2>Danh mục trang phục</h2><p>Phân loại váy cưới, vest, áo dài và phụ kiện.</p></div><button class="btn-primary-soft" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openCategory()"><i class="fas fa-plus"></i>Thêm danh mục</button></div>

<form class="filter-bar" method="get">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input name="q" placeholder="Tìm theo tên danh mục, mô tả..." value="<?= htmlspecialchars($keyword) ?>">
    </div>
    <select name="filter">
        <option value="">Tất cả danh mục</option>
        <option value="has_costumes" <?= $filter==='has_costumes'?'selected':'' ?>>Có trang phục</option>
        <option value="no_costumes" <?= $filter==='no_costumes'?'selected':'' ?>>Chưa có trang phục</option>
    </select>
    <button class="filter-submit">Lọc</button>
    <a href="categories.php">Đặt lại</a>
</form>

<section class="panel table-panel">
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Tên danh mục</th><th>Mô tả</th><th>Số trang phục</th><th class="text-end">Thao tác</th></tr></thead><tbody>
<?php if($data&&$data->num_rows):while($row=$data->fetch_assoc()):?>
<tr><td><strong><?= htmlspecialchars($row['name']) ?></strong></td><td><?= htmlspecialchars($row['description']?:'Chưa có mô tả') ?></td><td><span class="soft-badge"><?= number_format($row['total_costumes']) ?> mẫu</span></td><td class="text-end"><button class="icon-btn" onclick='openCategory(<?= json_encode($row,JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'><i class="fas fa-pen"></i></button><button class="icon-btn danger" onclick="removeCategory(<?= $row['id'] ?>)"><i class="fas fa-trash"></i></button></td></tr>
<?php endwhile;else:?><tr><td colspan="4" class="empty">Không tìm thấy danh mục nào.</td></tr><?php endif;?>
</tbody></table></div>
<?php if($pages>1):?><div class="pagination-wrap"><?php for($i=1;$i<=$pages;$i++):?><a class="<?= $i===$page?'active':'' ?>" href="?page=<?= $i ?>&q=<?= urlencode($keyword) ?>&filter=<?= urlencode($filter) ?>"><?= $i ?></a><?php endfor;?></div><?php endif;?>
</section>

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
