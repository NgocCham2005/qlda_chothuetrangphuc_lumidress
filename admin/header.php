<?php
session_start();
require_once '../config.php';
if(!isset($_SESSION['admin'])){header('Location: login.php');exit;}
$currentPage=strtolower(basename($_SERVER['PHP_SELF']));
$pageTitles=[
'index.php'=>'Tổng quan',
'costumes.php'=>'Quản lý trang phục',
'categories.php'=>'Danh mục',
'customers.php'=>'Khách hàng',
'rentals.php'=>'Phiếu thuê',
'payments.php'=>'Thanh toán',
'schedule.php'=>'Lịch thuê'
];
$pageTitle=$pageTitles[$currentPage]??'Quản trị Lumi Dress';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> - Lumi Dress</title>
<link rel="icon" href="../assets/images/favicon.svg" type="image/svg+xml">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=20260716">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="admin-page">
<div class="admin-overlay" id="adminOverlay"></div>
<aside class="admin-sidebar" id="adminSidebar">
<a class="admin-brand" href="index.php"><img src="../assets/images/logo-white.svg" alt="Lumi Dress"></a>
<div class="sidebar-note"><span>Hệ thống quản trị</span><strong>Thuê trang phục</strong></div>
<nav class="admin-nav">
<a class="<?= $currentPage==='index.php'?'active':'' ?>" href="index.php"><i class="fas fa-chart-pie"></i><span>Tổng quan</span></a>
<a class="<?= $currentPage==='schedule.php'?'active':'' ?>" href="schedule.php"><i class="fas fa-calendar-days"></i><span>Lịch thuê</span></a>
<a class="<?= $currentPage==='rentals.php'?'active':'' ?>" href="rentals.php"><i class="fas fa-file-signature"></i><span>Phiếu thuê</span></a>
<a class="<?= $currentPage==='costumes.php'?'active':'' ?>" href="costumes.php"><i class="fas fa-shirt"></i><span>Trang phục</span></a>
<a class="<?= $currentPage==='categories.php'?'active':'' ?>" href="categories.php"><i class="fas fa-layer-group"></i><span>Danh mục</span></a>
<a class="<?= $currentPage==='customers.php'?'active':'' ?>" href="customers.php"><i class="fas fa-users"></i><span>Khách hàng</span></a>
<a class="<?= $currentPage==='payments.php'?'active':'' ?>" href="payments.php"><i class="fas fa-wallet"></i><span>Thanh toán</span></a>
</nav>
<div class="sidebar-user">
<div class="sidebar-avatar"><i class="fas fa-user-shield"></i></div>
<div><strong><?= htmlspecialchars($_SESSION['admin']['fullname']) ?></strong><span>Quản trị viên</span></div>
<a href="../logout.php"><i class="fas fa-right-from-bracket"></i></a>
</div>
</aside>
<main class="admin-main">
<header class="admin-topbar">
<div class="topbar-left">
<button type="button" id="adminMenuToggle"><i class="fas fa-bars"></i></button>
<div><span>Lumi Dress</span><h1><?= htmlspecialchars($pageTitle) ?></h1></div>
</div>
<div class="topbar-actions">
<span><i class="far fa-calendar"></i><?= date('d/m/Y') ?></span>
<a href="rentals.php?add=1" title="Tạo phiếu thuê"><i class="fas fa-plus"></i></a>
</div>
</header>
<div class="admin-content">