<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$localAddresses = ['127.0.0.1','::1'];
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $localAddresses, true)) {
    http_response_code(403);
    exit('Setup admin hanya dapat dilakukan dari komputer server.');
}
$adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='admin'")->fetchColumn();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminCount === 0) {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if ($name === '' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12) {
        $error = 'Isi nama, email valid, dan kata sandi minimal 12 karakter.';
    } else {
        try {
            $roleId = $pdo->query("SELECT id FROM roles WHERE name='admin'")->fetchColumn();
            $pdo->prepare('INSERT INTO users (role_id,name,email,password) VALUES (?,?,?,?)')->execute([$roleId,$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
            log_activity($pdo, (int) $pdo->lastInsertId(), 'initial_admin_created');
            set_flash('Admin pertama berhasil dibuat. Silakan masuk.');
            redirect(base_url('auth/login.php'));
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'Email telah terdaftar.';
            } else {
                error_log('Initial admin setup failed: ' . $exception->getMessage());
                $error = 'Admin belum berhasil dibuat.';
            }
        }
    }
}
$pageTitle = 'Setup Admin';
require __DIR__ . '/includes/header.php';
?>
<section class="section-space"><div class="container"><div class="row justify-content-center"><div class="col-lg-6"><div class="content-card">
    <p class="eyebrow">Setup awal · akses lokal</p><h1 class="h3 fw-bold">Buat admin pertama</h1>
    <?php if ($adminCount > 0): ?><div class="alert alert-success">Setup sudah selesai. Demi keamanan, hapus atau ganti nama file <code>setup-admin.php</code>.</div>
    <?php else: ?><?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <div class="alert alert-warning">Halaman ini hanya menerima koneksi lokal dan hanya aktif sebelum admin pertama dibuat. Setelah berhasil, hapus file setup ini.</div>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="mb-3"><label class="form-label">Nama</label><input class="form-control" name="name" required></div><div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div><div class="mb-3"><label class="form-label">Kata sandi (minimal 12 karakter)</label><input class="form-control" type="password" name="password" minlength="12" required autocomplete="new-password"></div><button class="btn btn-primary">Buat admin</button></form>
    <?php endif; ?>
</div></div></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
