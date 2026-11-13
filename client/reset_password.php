
<?php
require_once '../customer_auth.php';

$error = '';
$success = false;

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'Liên kết khôi phục không hợp lệ.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_customer_csrf()) {
        $error = 'Yêu cầu không hợp lệ.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 8) {
            $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
        } elseif ($password !== $confirm) {
            $error = 'Mật khẩu xác nhận không khớp.';
        } else {
            $tokenHash = hash('sha256', $token);

            $stmt = $conn->prepare(
                "SELECT id, customer_id FROM password_resets
                 WHERE token_hash = ?
                 AND used = 0
                 AND expires_at > NOW()
                 LIMIT 1"
            );
            $stmt->bind_param("s", $tokenHash);
            $stmt->execute();

            $reset = $stmt->get_result()->fetch_assoc();

            if (!$reset) {
                $error = 'Liên kết đã hết hạn hoặc đã được sử dụng.';
            } else {
                $conn->begin_transaction();

                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $customerId = (int)$reset['customer_id'];
                    $resetId = (int)$reset['id'];

                    $update = $conn->prepare(
                        "UPDATE customers SET password = ? WHERE id = ?"
                    );
                    $update->bind_param("si", $hash, $customerId);
                    $update->execute();

                    $used = $conn->prepare(
                        "UPDATE password_resets SET used = 1
                         WHERE customer_id = ? AND used = 0"
                    );
                    $used->bind_param("i", $customerId);
                    $used->execute();

                    $conn->commit();
                    $success = true;
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = 'Không thể cập nhật mật khẩu. Vui lòng thử lại.';
                }
            }
        }
    }
}

include 'header.php';
?>

<div class="account-container">
    <div class="account-card">
        <h2>Đặt lại mật khẩu</h2>

        <?php if ($success): ?>
            <div class="account-message">
                Đổi mật khẩu thành công!
                <a href="login.php">Đăng nhập ngay</a>
            </div>
        <?php else: ?>

            <?php if ($error): ?>
                <div class="account-error"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if (preg_match('/^[a-f0-9]{64}$/', $token)): ?>
                <form method="POST">
                    <?= customer_csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <label>Mật khẩu mới</label>
                    <input type="password" name="password" required>

                    <label>Xác nhận mật khẩu</label>
                    <input type="password" name="confirm_password" required>

                    <button class="account-button" type="submit">
                        Xác nhận đổi mật khẩu
                    </button>
                </form>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
