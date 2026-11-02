<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $stmt = $pdo->prepare('SELECT u.id, u.name, u.email, u.password, u.is_active, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ? LIMIT 1');
    $stmt->execute([$email]);
    $account = $stmt->fetch();
    if ($account && (int) $account['is_active'] === 1 && password_verify($password, $account['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int) $account['id'], 'name' => $account['name'], 'email' => $account['email'], 'role' => $account['role']];
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$account['id']]);
        log_activity($pdo, (int) $account['id'], 'login');
        $destination = in_array($account['role'],['admin','committee'],true)
            ? 'admin/index.php'
            : (isset($_SESSION['selected_level_id']) ? 'student/application.php' : 'student/dashboard.php');
        redirect(base_url($destination));
    }
    $error = 'Email atau kata sandi tidak sesuai.';
}
$pageTitle = 'Masuk';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-wrap">
    <div class="container"><div class="row justify-content-center align-items-stretch g-3"><div class="col-lg-5"><aside class="auth-aside h-100 d-flex flex-column justify-content-between"><div><div class="feature-icon mb-4"><i class="fa-solid fa-bolt"></i></div><p class="text-uppercase small fw-bold opacity-75">Your next chapter</p><h2 class="display-6 fw-bold">Lanjutkan cerita belajarmu.</h2><p class="opacity-75">Lihat status pendaftaran dan pengumuman terbaru lewat satu dashboard.</p></div><div class="small opacity-75">Selamat datang kembali di <?= e($schoolName ?? 'portal PPDB') ?>.</div></aside></div><div class="col-lg-5">
        <div class="auth-card">
            <p class="eyebrow mb-2">Selamat datang kembali</p><h1 class="h3 fw-bold mb-4">Masuk ke akun PPDB</h1>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form method="post" data-loading>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></div>
                <div class="mb-4"><label class="form-label" for="password">Kata sandi</label><input class="form-control" type="password" id="password" name="password" required autocomplete="current-password"></div>
                <button class="btn btn-primary w-100" type="submit">Masuk</button>
            </form>
            <p class="text-center text-secondary mt-4 mb-0">Belum punya akun? <a href="<?= e(base_url('auth/register.php')) ?>">Daftar sekarang</a></p>
        </div>
    </div></div></div></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
