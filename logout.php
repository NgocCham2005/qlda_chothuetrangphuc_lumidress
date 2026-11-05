<?php
session_start();
session_unset();
session_destroy();
require_once 'config.php';
header('Location: '.ADMIN_URL.'login.php');
exit;
?>