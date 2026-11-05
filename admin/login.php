<?php
session_start();
require_once '../config.php';
if(isset($_SESSION['admin'])){header('Location: index.php');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $username=trim($_POST['username']??'');
    $password=$_POST['password']??'';
    $stmt=$conn->prepare("SELECT * FROM admins WHERE username=? LIMIT 1");
    $stmt->bind_param('s',$username);
    $stmt->execute();
    $admin=$stmt->get_result()->fetch_assoc();
    if($admin&&password_verify($password,$admin['password'])){
        $_SESSION['admin']=['id'=>$admin['id'],'fullname'=>$admin['fullname'],'username'=>$admin['username']];
        header('Location: index.php');exit;
    }
    $error='Tên đăng nhập hoặc mật khẩu không đúng';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đăng nhập quản trị - Lumi Dress</title>
<link rel="icon" href="../assets/images/favicon.svg" type="image/svg+xml">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="login-page">
<div class="login-shell">
<div class="login-visual">
<img src="../assets/images/logo-white.svg" alt="Lumi Dress">
<h1>Quản lý thuê trang phục</h1>
<p>Kiểm soát trang phục, lịch thuê, khách hàng và thanh toán trên một hệ thống.</p>
<div class="login-points">
<span><i class="fas fa-calendar-check"></i> Chặn lịch thuê trùng</span>
<span><i class="fas fa-shirt"></i> Theo dõi size và tình trạng</span>
<span><i class="fas fa-receipt"></i> Quản lý cọc và thanh toán</span>
</div>
</div>
<form method="post" class="login-card">
<div class="login-card-head">
<span>Quản trị viên</span>
<h2>Đăng nhập hệ thống</h2>
<p>Nhập thông tin tài khoản để tiếp tục.</p>
</div>
<?php if($error):?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif;?>
<label>Tên đăng nhập</label>
<div class="input-icon"><i class="fas fa-user"></i><input type="text" name="username" class="form-control" required autofocus></div>
<label>Mật khẩu</label>
<div class="input-icon"><i class="fas fa-lock"></i><input type="password" name="password" class="form-control" required></div>
<button type="submit" class="login-btn">Đăng nhập <i class="fas fa-arrow-right"></i></button>
<div class="login-demo">Tài khoản mẫu: <strong>admin</strong> · Mật khẩu: <strong>123456</strong></div>
</form>
</div>
</body>
</html>