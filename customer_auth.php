
<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function customer_logged_in() {
    return isset($_SESSION['customer_id']);
}

function require_customer_login() {
    if (!customer_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function customer_csrf_field() {
    if (empty($_SESSION['customer_csrf'])) {
        $_SESSION['customer_csrf'] = bin2hex(random_bytes(32));
    }

    echo '<input type="hidden" name="csrf_token" value="' .
        e($_SESSION['customer_csrf']) . '">';
}

function verify_customer_csrf() {
    return isset($_POST['csrf_token'], $_SESSION['customer_csrf'])
        && hash_equals($_SESSION['customer_csrf'], $_POST['csrf_token']);
}

function customer_redirect($page) {
    header('Location: ' . $page);
    exit;
}
